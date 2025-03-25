<?php

$subcommands = [
    "system" => function(){
        global $application;
        global $argc;
        global $argv;
        $format='list';
        
        $argParser=create_argParser();
        add_parser_option($argParser,"j");
        parse_args($argParser,$argc,$argv);
        
        if(option_is_set($argParser,"j")){
            $format='json';
        }
        
        $serviceLocator = $application->getServiceManager();
        $controller = new Omeka\Controller\Admin\SystemInfoController(
            $serviceLocator->get('Omeka\Connection'),
            $serviceLocator->get('Config'),
            $serviceLocator->get('Omeka\Cli'),
            $serviceLocator->get('Omeka\ModuleManager')
        );
        $model = $controller->browseAction();
        $info = $model->getVariable('info');

        output($info, $format);
    },
    "db" => function(){
        global $application;
        global $argc;
        global $argv;
        $format='list';
        
        $argParser=create_argParser();
        add_parser_option($argParser,"j");
        parse_args($argParser,$argc,$argv);
        
        if(option_is_set($argParser,"j")){
            $format='json';
        }
        
        $serviceLocator = $application->getServiceManager();
        
        $settings = $serviceLocator->get('ApplicationConfig');
        output($settings['connection'], $format);
    },
];
