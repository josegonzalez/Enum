<?php
namespace Enum\Model\Behavior;

use ArrayObject;
use Cake\ORM\Behavior;
use Enum\Model\Behavior\Strategy\AbstractStrategy;
use InvalidArgumentException;
use RuntimeException;

class EnumBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * - `defaultStrategy`: the default strategy to use.
     * - `implementedMethods`: custom table methods made accessible by this behavior.
     * - `providers`: the defined enumeration lists. Providers can use different strategies,
     *   use prefixes to differentiate them (defaults to the uppercased provider name) and
     *   are persisted into a table's field (default to the underscored provider name).
     *
     *   Example:
     *
     *   ```php
     *   $providers = [
     *       'priority' => [
     *           'strategy' => 'lookup',
     *           'prefix' => 'PRIORITY',
     *           'field' => 'priority',
     *       ],
     *   ];
     *   ```
     *
     * @var array
     */
    protected $_defaultConfig = [
        'defaultStrategy' => 'lookup',
        'implementedMethods' => [
            'enum' => 'enum',
        ],
        'providers' => [],
    ];

    /**
     * Class map.
     *
     * @var array
     */
    protected $_classMap = [
        'lookup' => 'Enum\Model\Behavior\Strategy\LookupStrategy',
        'const' => 'Enum\Model\Behavior\Strategy\ConstStrategy',
        'enum' => 'Enum\Model\Behavior\Strategy\EnumStrategy',
    ];

    /**
     * Stack of strategies in use.
     *
     * @var array
     */
    protected $_strategies = [];

    /**
     * Initializes the behavior.
     *
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->_normalizeConfig();
    }

    /**
     * Marshaller's callback.
     *
     * @return void
     */
    public function beforeMarshal(Event $event, ArrayObject $data, ArrayObject $options)
    {
        foreach ($this->config('providers') as $provider => $config) {
            if (empty($data[$config['field']])) {
                continue;
            }

            $data[$config['field']] = $this->strategy($provider, $config['strategy'])
                ->get($data[$config['field']]);
        }
    }

    /**
     * Getter/setter for strategies.
     *
     * @param string $alias
     * @param mixed $strategy Strategy name from the classmap,
     * @return \Enum\Model\Behavior\Strategy\AbstractStrategy
     */
    public function strategy($alias, $strategy)
    {
        if (!empty($this->_strategies[$alias])) {
            return $this->_strategies[$alias];
        }

        $this->_strategies[$alias] = $strategy;
        if (!($strategy instanceof AbstractStrategy)) {
            if (isset($this->_classMap[$strategy])) {
                $class = $this->_classMap[$strategy];
            }

            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('Class not found for strategy (%s)', $strategy));
            }

            $this->_strategies[$alias] = new $class($alias, $this->_table);
        }

        return $this->_strategies[$alias];
    }

    /**
     * Normalizes the providers configuration and initializes the strategies.
     *
     * @return void
     */
    protected function _normalizeConfig()
    {
        $providers = $this->config('providers');
        $strategy = $this->config('defaultStrategy');

        foreach ($providers as $alias => $options) {
            if (is_numeric($alias)) {
                unset($providers[$alias]);
                $alias = $options;
                $options = [];
                $providers[$alias] = $options;
            }

            if (is_string($options)) {
                $options = ['prefix' => strtoupper($options)];
            }

            if (empty($options['strategy'])) {
                $options['strategy'] = $strategy;
            }

            $providers[$alias] =  $this->strategy($alias, $options['strategy'])
               ->initialize($options);
        }

        $this->config('providers', $providers, false);
    }

    /**
     * @param string $group Defined group name.
     * @return array
     */
    public function enum($group)
    {
        $config = $this->config('providers.' . $group);
        if (empty($config)) {
            throw new RuntimeException();
        }

        return $this->strategy($group, $config['strategy'])->enum($config);
    }
}
