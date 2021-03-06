<?php
/**
 * This file is part of the CLIFramework package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace CLIFramework;
use Exception;
use CLIFramework\CommandInterface;

/**
 * abstract command class
 *
 */
abstract class Command extends CommandBase
    implements CommandInterface
{
    /**
     * @var CLIFramework\Application Application object.
     */
    public $application;

    /**
     * @var string Command alias string.
     */
    public $alias;

    public $name;

    public function __construct($parent = null)
    {
        // this variable is optional (for backward compatibility)
        if ($parent) {
            $this->setParent($parent);
        }
        parent::__construct();
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setApplication($application)
    {
        $this->application = $application;
    }

    public function getApplication() {
        if ( $this->application ) {
            return $this->application;
        }
        while ( true ) {
            $p = $this->parent;
            if ( ! $p ) {
                return null;
            }
            if ( $p instanceof \CLIFramework\Application ) {
                return $p;
            }
        }
    }

    /**
     * Translate current class name to command name.
     *
     * @return string command name
     */
    public function getName()
    {
        if ( $this->name ) {
            return $this->name;
        }

        // get default command name
        $class = get_class($this);

        // strip command suffix
        $parts = explode('\\',$class);
        $class = end($parts);
        $class = preg_replace( '/Command$/','', $class );
        return strtolower( preg_replace( '/(?<=[a-z])([A-Z])/', '-\1' , $class ) );
    }

    /**
     * Returns logger object.
     *
     * @return CLIFramework\Logger
     */
    public function getLogger()
    {
        return $this->getApplication()->getLogger();
    }



    /**
     * Returns text style formatter.
     *
     * @return CLIFramework\Formatter
     */
    public function getFormatter()
    {
        return $this->getApplication()->getFormatter();
    }

    /**
     * Alias setter
     *
     * @param string $alias
     */
    public function alias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Provide a shorthand property for retrieving logger object.
     *
     * @param string $k property name
     */
    public function __get($k)
    {
        if ($k === 'logger') {
            return $this->getLogger();
        }
        elseif( $k === 'formatter' ) {
            return $this->getFormatter();
        }
        throw new Exception( "$k is not defined." );
    }

}
