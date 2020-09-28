<?php

/**
 * Driver.php - class to manage the registerable drivers
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License
 * version 3 as published by the Free Software Foundation.
 *
 * @author      Till Glöggler <tgloeggl@uos.de>
 * @license     https://www.gnu.org/licenses/agpl-3.0.html AGPL version 3
 */

namespace ElanEv\Model;

use MeetingPlugin;
use ElanEv\Driver\DriverFactory;

class Driver
{
    static
        $config;

    static function discover($toArray = false)
    {
        $drivers = array();

        foreach (glob(__DIR__ . '/../../Driver/*.php') as $filename) {
            $class = 'ElanEv\\Driver\\' . substr(basename($filename), 0, -4);
            $title = '';
            $config_options = [];
            $recording_options = [];
            if (in_array('ElanEv\Driver\DriverInterface', class_implements($class)) !== false) {
                $title          = substr(basename($filename), 0, -4);
                $config_options = $class::getConfigOptions();
            }

            if (in_array('ElanEv\Driver\RecordingInterface', class_implements($class)) !== false) {
                //If there is RecordingInterface then the field 'record' is considered as a must later on in the logic
                //that means, if admin set record to true then every other setting like opencast can be used
                $recording_options['record'] = new \ElanEv\Driver\ConfigOption('record', dgettext(MeetingPlugin::GETTEXT_DOMAIN, 'Aufzeichnungen zulassen'), false);
                if ($oc_config = $class::useOpenCastForRecording()) {
                    $recording_options['opencast'] = $oc_config;
                }
            }

            if ($title && $config_options) {
                $drivers[$title] = array(
                    'title'     => $title,
                    'config'    => $toArray ? self::convertDriverConfigToArray($config_options) : $config_options,
                );

                !$recording_options ?:  $drivers[$title]['recording'] = $toArray ? self::convertDriverConfigToArray($recording_options) : $recording_options;
            }
        }

        return $drivers;
    }

    static function loadConfig()
    {
        if (!self::$config) {
            self::$config = json_decode(\Config::get()->getValue('VC_CONFIG'), true);
        }

        foreach (self::$config as $driver_name => $config) {
            $class = 'ElanEv\\Driver\\' . $driver_name;
            if (in_array('ElanEv\Driver\DriverInterface', class_implements($class)) !== false) {
                if ($create_features = $class::getCreateFeatures()) {
                    self::$config[$driver_name]['features']['create'] = self::convertDriverConfigToArray($create_features);
                }
            }
            if (in_array('ElanEv\Driver\RecordingInterface', class_implements($class)) !== false) {
                if (!empty($record_feature = $class::getRecordFeature())) {
                    self::$config[$driver_name]['features']['record'] = self::convertDriverConfigToArray($record_feature);
                }
            }
        }
    }

    static function convertDriverConfigToArray($config_options)
    {
        $array = [];
        foreach ($config_options as $option) {
            $array[] = $option->toArray();
        }
        return $array;
    }

    static function getConfigByDriver($driver_name, $config_options)
    {
        self::loadConfig();

        $new_config = array();

        foreach ($config_options as $config) {
            if ($value = self::$config[$driver_name][0][$config->getName()]) {
                $config->setValue($value);
            }

            $new_config[$config->getName()] = $config;
        }

        return $new_config;
    }

    static function setConfigByDriver($driver_name, $config_options)
    {
        self::loadConfig();

        $valid_servers = true;
        if ($config_options['enable'] == 1 && (isset($config_options['servers']) && !count($config_options['servers']) || !isset($config_options['servers']))) {
            $config_options['enable'] = 0;
            $valid_servers = false;
        }
        $approved_servers = [];
        foreach ($config_options as $key => $value) {
            if ($key == 'servers') {
                $config_tmp[$driver_name] = $config_options;
                $config_tmp[$driver_name]['enable'] = 1;
                $driver_factory = new DriverFactory($config_tmp);
                foreach ($value as $index => $server_info) {
                    if (empty(trim($server_info['url']))) {
                        $valid_servers = false;
                        continue;
                    }
                    $driver = $driver_factory->getDriver($driver_name, $index);
                    if ($driver->checkServer()) {
                        $approved_servers[] = $server_info;
                    } else {
                        $valid_servers = false;
                    }
                }
                self::$config[$driver_name][$key] = $approved_servers;
            } else {
                self::$config[$driver_name][$key] = $value;
            }
        }

        if (count($approved_servers) == 0) {
            self::$config[$driver_name]['enable'] = 0;
        }

        \Config::get()->store('VC_CONFIG', json_encode(self::$config));

        return $valid_servers;
    }

    static function getConfig()
    {
        self::loadConfig();

        return self::$config;
    }

    static function getConfigValueByDriver($driver_name, $key)
    {
        $config = json_decode(\Config::get()->getValue('VC_CONFIG'), true);

        foreach ($config as $dname => $dvals) {
            if ($driver_name == $dname && isset($dvals[$key])) {
                return $dvals[$key];
            }
        }

        return false;
    }
}
