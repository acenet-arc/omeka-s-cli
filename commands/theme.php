<?php

function get_local_theme($theme_name, $reload=false){
    global $application;
    $serviceLocator = $application->getServiceManager();

    if($reload){
        $factory = new Omeka\Service\ThemeManagerFactory();
        $themeManager = $factory->__invoke($serviceLocator, '');
    }else{
        $themeManager = $serviceLocator->get('Omeka\Site\ThemeManager');
    }
    $themes = $themeManager->getThemes();
    return($themes[$theme_name]);
}
function theme_is_installed($theme_name){
    return (bool)(get_local_theme($theme_name));
}
function theme_download($theme, $api_theme=null){
    $theme_name = $theme->getName();
    if($api_theme==null)
        $api_theme = $api_themes[$theme_name];

    $download_url = $api_theme['versions'][$api_theme['latest_version']]['download_url'];
    if($download_url){
        download_unzip($download_url, OMEKA_PATH.'/themes/');
    }else{
        print_error("Could not download theme");
    }
}
function theme_install($theme){
    global $application;
    $services = $application->getServiceManager();
    $themeManager = $services->get('Omeka\Site\ThemeManager');

    try {
        $themeManager->install($theme);
    } catch (Exception $e) {
        print_error("Could not install theme: \n" . $e->getMessage());
    }
}
function theme_delete($theme,$force=false){
    global $application;
    $serviceLocator = $application->getServiceManager();
    try {
        $path = $theme->getPath();
        if(empty($path)||$path=='/'||!(str_contains($path, 'themes')))
            print_error('Incorrect or dangerous path detected. Please remove the folder manually.');

        // Check for usage
        $sites = get_sites();
        $childTheme = $theme->getIni()['child_theme']??false;
        if($childTheme)
            $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        
        // Check if it's in use as the main theme
        foreach ($sites as $site) {
            if($site->getTheme()==$theme->getId()){
                print_error('The theme is being used on the site ' . $site->getSlug());
            }

            // If it's a child theme, check if it's used in a site as such
            if($childTheme){
                $childThemes = $siteSettings->get("libnamic_suite_child_themes", [], $site->getId());
                if(!empty($childThemes)){
                    if(in_array($theme->getId(), $childThemes)){
                        print_error('The theme is being used as a child theme on the site ' . $site->getSlug());
                    }
                }
            }
        }

        if(!$force){
            $answer = readline('Are you sure that you want to delete the directory "' . $path . '" and its contents? > ');
            if(($answer=='y')||($answer=='y')){
                system("rm -rf ".escapeshellarg($path));
            }
        }
        else{
            
            system("rm -rf ".escapeshellarg($path));
        }
    } catch (Exception $e) {
        print_error("Could not delete theme: \n" . $e->getMessage());
    }
}



$subcommands = [
    "list" => function(){
        global $application;
        global $argc;
        global $argv;
        $mode = 'human';
        $format='table';
        
        $argParser=create_argParser();
        add_parser_option($argParser,"j");
        parse_args($argParser,$argc,$argv);
        
        if(option_is_set($argParser,"j")){
            $mode='raw';
            $format='json';
        }
        
        $services = $application->getServiceManager();
        $omekaThemes = $services->get('Omeka\Site\ThemeManager');
        $themes = $omekaThemes->getThemes();
        $api_themes = get_theme_list_from_api();
        $themes_array = [];
        foreach ($themes as $key => $theme) {
            $themes_array[$key] = [
                'id' => $theme->getId(),
                'name' => $theme->getName(),
                'state' => $theme->getState(),
                'ini' => $theme->getIni(),
                'version' => $theme->getIni()['version'],
                'author' => $theme->getIni()['author'],
                'path' => $theme->getPath(),
                'Libnamic child theme' => $theme->getIni()['child_theme']??false,
                'isConfigurable' => $theme->isConfigurable(),
                'isConfigurableResourcePageBlocks' => $theme->isConfigurableResourcePageBlocks(),
                'upgrade_available' => ($theme->getIni()?($theme->getIni()['version']!=$api_themes[$theme->getId()]['latest_version']?$api_themes[$theme->getId()]['latest_version']:'up to date'):'')??'',
            ];
        }
        if($mode == 'raw'){
            output($themes_array, $format);
        }else{
            $result_array = [];
            foreach ($themes_array as $key => $theme) {
                $result_array[] = [
                    'id' => $theme['id'],
                    'name' => $theme['name'],
                    'state' => $theme['state'],
                    'version' => $theme['version'],
                    'author' => $theme['author'],
                    'Libnamic child theme' => $theme['Libnamic child theme'],
                    'upgrade_available' =>  $theme['upgrade_available'],

                ];
            }
            output($result_array, $format);
        }
    },
    "download" => function(){
        global $argv;
        global $argc;
        $theme = $argv[3];
        
        $argParser=create_argParser();
        add_parser_option($argParser,"f");
        parse_args($argParser,$argc,$argv);
        
        $force = false;
        if(option_is_set($argParser,"f")){
            
            $force = true;
        }
        
        $version_to_download = '';

        if(empty($theme)){
            print_error('Theme not specified. Please specify a theme ID or URL');
        }

        if(str_starts_with($theme, 'http')){ // URL
            $download_url = $theme;
        }else{
            $api_themes = get_theme_list_from_api();
            if(!key_exists($theme, $api_themes)){
                print_error('Theme not found in the official theme list. Please specify a valid theme ID or the URL for a custom theme. Available themes are:', false);
                $theme_list = [];
                foreach ($api_themes as $key => $theme) {
                    $theme_list[] = [
                        'ID' =>  $theme['dirname'],
                        'Latest version' => $theme['latest_version'],
                        'Owner' => $theme['owner'],
                    ];
                }
                print_error(output($theme_list, 'table', true));
            }else{
                if(theme_is_installed($theme) && !$force){
                    print_error('The theme seems to be already downloaded. Use the flag -f in order to download it anyway.');
                }
                if(empty($version_to_download))
                    $version_to_download = $api_themes[$theme]['latest_version'];
                $download_url = $api_themes[$theme]['versions'][$version_to_download]['download_url'];
            }

        }
        
        // Download and unzip
        download_unzip($download_url, OMEKA_PATH.'/themes/');
        
    },
    "delete" => function(){
        global $argc;
        global $argv;
        $force=false;
        
        $argParser=create_argParser();
        add_parser_option($argParser,"f");
        parse_args($argParser,$argc,$argv);
        
        if(option_is_set($argParser,"f")){
            $force=true;
        }
        $theme_name = $argv[3];
        $theme = get_local_theme($theme_name);

        elevate_privileges();
        
        if(!$theme){
            print_error("Theme not found");
        }else{
            theme_delete($theme,$force);
        }
    },
    "update" => function(){
        global $argv;
        $theme_name = $argv[3];
        $theme = get_local_theme($theme_name);

        $die=true;
        $no_error_code=0;
        elevate_privileges();

        $api_theme = get_theme_list_from_api()[$theme->getId()];

        if(!$theme){
            print_error("Theme not found");
        }

        if($api_theme){
            if($theme->getIni()['version']==$api_theme['latest_version']){
                print_error('The theme does not seem to have available updates',$die,$no_error_code);
            }else{
                // Download latest version and upgrade the theme
                theme_download($theme, $api_theme);
                echo("Newest version of the theme downloaded and extracted.\n");
            }
        }

        
    },
    "status" => function(){
        global $application;
        global $argv;
        global $argc;
        $format = 'table';
        $mode = 'human';
        
        $argParser=create_argParser();
        add_parser_option($argParser,"j");
        parse_args($argParser,$argc,$argv);
        
        if(option_is_set($argParser,"j")){
            $mode='raw';
        }
        $services = $application->getServiceManager();
        $omekaThemes = $services->get('Omeka\Site\ThemeManager');
        $themes = $omekaThemes->getThemes();

        if(key_exists($argv[3], $themes)){
            $theme = $themes[$argv[3]];
            if($mode == 'raw'){
                output($themes_array, 'json');
            }else{
                $theme = [
                    'id' => $theme->getId(),
                    'name' => $theme->getName(),
                    'state' => $theme->getState(),
                    'version' => $theme->getIni()['version'],
                    'author' => $theme->getIni()['author'],
                    'path' => $theme->getPath(),
                    'Libnamic child theme' => $theme->getIni()['child_theme']??false,
                    'isConfigurable' => $theme->isConfigurable(),
                    'isConfigurableResourcePageBlocks' => $theme->isConfigurableResourcePageBlocks(),
                ];
                if($format=='table'){
                    $theme = [$theme];
                }
                output($theme, $format);
            }
            
        }else{
            echo("Theme {$argv[3]} not found\n");
            exit(1);
        }
        
    },
];
