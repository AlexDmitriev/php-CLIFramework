<?php
/*
 * This file is part of the {{ }} package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
namespace CLIFramework;

use GetOptionKit\ContinuousOptionParser;
use GetOptionKit\OptionSpecCollection;

use CLIFramework\CommandLoader;
use CLIFramework\CommandBase;
use CLIFramework\Logger;

use Exception;

class Application extends CommandBase
{
    // options parser
    public $optionParser;

    function __construct()
    {
        parent::__construct();

        // get current class namespace, add {App}\Command\ to loader
        $app_ref_class = new \ReflectionClass($this);
        $app_ns = $app_ref_class->getNamespaceName();

        $this->loader = new CommandLoader();
        $this->loader->addNamespace( array( '\\CLIFramework\\Command' ) );
        $this->loader->addNamespace( '\\' . $app_ns . '\\Command' );
        $this->optionsParser = new ContinuousOptionParser;
    }


    /**
     * register application option specs to the parser
     */
    public function options($opts)
    {
        $opts->add('v|verbose');
        $opts->add('d|debug');
    }


    /* 
     * init application,
     *
     * users register command mapping here. (command to class name)
     */
    public function init()
    {
        // $this->registerCommand('list','\\CLIFramework\\Command\\ListCommand');
        $this->registerCommand('help','\\CLIFramework\\Command\\HelpCommand');
    }


    /**
     * run application with 
     * list argv 
     *
     * @param Array $argv
     *
     * */
    public function run(Array $argv)
    {

        $current_cmd = $this;

        // init application 
        $current_cmd->init();

        // use getoption kit to parse application options
        $getopt = $this->optionsParser;
        $specs = new OptionSpecCollection;
        $getopt->setSpecs( $specs );

        // init application options
        $current_cmd->options($specs);

        // save options specs
        $current_cmd->optionSpecs = $specs;

        // save options result
        $current_cmd->options = $getopt->parse( $argv );
        $current_cmd->prepare();

        $command_stack = array();
        $arguments = array();
        $subcommand_list = $current_cmd->getCommandList();
        $parent = null;
        while( ! $getopt->isEnd() ) {

            // check current argument is a subcommand name 
            // or normal arguments by given a subcommand list.
            if( in_array(  $getopt->getCurrentArgument() , $subcommand_list ) ) 
            {
                $subcommand = $getopt->getCurrentArgument();
                $getopt->advance();

                // initialize subcommand (subcommand with parent command class)
                $command_class = $current_cmd->getCommandClass( $subcommand );
                if( ! $command_class ) {
                    if( end($command_stack) ) {
                        $command_class = $this->loader->loadSubcommand($subcommand, end($command_stack));
                    } 
                    else {
                        $command_class = $this->loader->load( $subcommand );
                    }
                }

                if( ! $command_class ) {
                    throw new Exception("command $subcommand not found.");
                }

                // if current_cmd is not application, we should save parent command object.
                $current_cmd = new $command_class();
                if( $current_cmd !== $this )
                    $parent = $current_cmd;

                // save parent command class.
                $current_cmd->parent = $parent;
                $current_cmd->application = $this;

                // let command has the command loader to register subcommand (load class)
                $current_cmd->loader = $this->loader;



                // init subcommand option
                $command_specs = new OptionSpecCollection;
                $getopt->setSpecs($command_specs);
                $current_cmd->options( $command_specs );
                $current_cmd->optionSpecs = $command_specs;

                // register subcommands
                $current_cmd->init();

                // parse options for command.
                $current_cmd_options = $getopt->continueParse();

                // run subcommand prepare
                $current_cmd->prepare();

                $current_cmd->options = $current_cmd_options;
                $command_stack[] = $current_cmd; // save command object into the stack

                // update subcommand list
                $subcommand_list = $current_cmd->getCommandList();

            } else {
                $arguments[] = $getopt->advance();
            }
        }

        // get last command and run
        if( $last_cmd = array_pop( $command_stack ) ) {
            $return = $last_cmd->execute( $arguments );
            $last_cmd->finish();
            while( $cmd = array_pop( $command_stack ) ) {
                // call finish stage.. of every command.
                $cmd->finish();
            }
        }
        else {
            // no command specified.
            return $this->execute( $arguments );
        }

        $current_cmd->finish();
    }


    public function execute( $arguments = array() )
    {
        // show list and help by default
        $help_class = $this->getCommandClass( 'help' );
        if( $help_class ) {
            $help = new $help_class;
            $help->parent = $this;
            $help->execute($arguments);
        }
        else {
            throw new Exception("Help command is not defined.");
        }
    }

}
