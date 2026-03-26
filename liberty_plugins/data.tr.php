<?php
/**
 * tr translation plugin
 *
 * @author     wjames5 will@tekimaki.com 
 * @version    $Revision$
 * @package    liberty
 * @subpackage plugins_data
 * @copyright  Copyright (c) 2008, bitweaver.org
 */

namespace Bitweaver\Liberty;
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyContent;

/**
 * Setup Code
 */
define( 'PLUGIN_GUID_DATATR', 'datatr' );
global $gLibertySystem;
$pluginParams = [
	'tag'           => 'tr',
	'auto_activate' => false,
	'requires_pair' => true,
	'load_function' => 'data_tr',
	'title'         => 'Translate',
	'help_page'     => 'DataPluginTR',
	'description'   => KernelTools::tra( "Use this plugin to mark strings for translation. You should only use this for common short strings, and not entire pages." ),
	'help_function' => 'data_tr_help',
	'syntax'        => "",
	'plugin_type'   => DATA_PLUGIN
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATATR, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATATR );

function data_tr_help() {
	$help = KernelTools::tra( "Example: " ) . "string of text to be translated";
	return $help;
}

function data_tr( $pData, $pParams, $pCommonObject ) {
	$transString = KernelTools::tra( $pData );
	$parseHash = $pCommonObject->mInfo;
	$parseHash['no_cache'] = true;
	$parseHash['data'] = $transString;
	$parsedData = LibertyContent::parseDataHash( $parseHash );
	$parsedData = preg_replace( '|<br\s*/?>$|', '', $parsedData );
	return $parsedData;
}
