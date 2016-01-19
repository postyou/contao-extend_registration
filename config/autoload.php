<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package Postyou_extend_registation
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
    'postyou',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
    // Classes
    'postyou\ModuleRegistrationExtended' => 'system/modules/extend_registration/classes/ModuleRegistrationExtended.php',
    'postyou\ModuleLoginExtended' => 'system/modules/extend_registration/classes/ModuleLoginExtended.php',
));
