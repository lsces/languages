<?php
/**
 * @package languages
 * @subpackage functions
 * @version $Header$
 *
 * Copyright (c) 2005 bitweaver.org
 * Copyright (c) 2004-2005, Christian Fowler, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 */

/**
 * Initialization
 */
use Bitweaver\KernelTools;
require_once '../kernel/includes/setup_inc.php';

$gBitSystem->verifyPermission( 'p_languages_edit' );

// Get available languages from DB
$languages = $gBitLanguage->listLanguages();
$gBitSmarty->assign('languages', $languages);

if( !empty( $_REQUEST['all_trans'] ) ) {
	$gBitSmarty->assign( 'allTrans', 1 );
}

if( !empty( $_REQUEST['un_trans'] ) ) {
	$gBitSmarty->assign( 'unTrans', 1 );
}

if( isset( $_REQUEST['save_translations'] ) ) {
	$editLang = $_REQUEST['lang'];
	$gBitLanguage->loadLanguage( $editLang );
	$storedStrings = null;
	foreach( $_REQUEST['edit_trans'] as $sourceHash => $string ) {
		if( $string != $gBitLanguage->mStrings[$editLang][$sourceHash]['trans'] ) {
			// we need to remove the $_REQUEST slashes here to avoid stuff like:
			// {$gBitSystem->getConfig(\'stuff\')} in the translated strings -
			// it will kill the site since smarty won't be able to interpret
			// the template anymore --xing
			if( ini_get( 'magic_quotes_gpc' ) ) {
				$string = stripslashes( $string );
			}
			$gBitLanguage->storeTranslationString( $editLang, $string, $sourceHash );
			// update string in template as well
			$tranStrings[$sourceHash]['trans'] = $string;
			// this has to be the source, otherwise the translated string will enter the db and be recognised as a used master
			$storedStrings[] = $gBitLanguage->mStrings[$editLang][$sourceHash]['source'];
		}
	}
	$tranStrings = $gBitLanguage->getTranslationString( $sourceHash, $editLang );
	$gBitSmarty->assign('tranStrings', $tranStrings );
	$gBitSmarty->assign( 'lang', $editLang );
	$gBitSmarty->assign( 'translate', true );
	$gBitSmarty->assign( 'saveSuccess', KernelTools::tra( "The following items have been saved successfully" ).":" );
	$gBitSmarty->assign( 'storedStrings', $storedStrings );
}

if( !empty( $_REQUEST['hash'] ) ) {
	$tranStrings = $gBitLanguage->getTranslationString( $_REQUEST['hash'], $editLang );
	$gBitSmarty->assign('tranStrings', $tranStrings );
} elseif( !empty( $_REQUEST['choose_lang'] ) ) {
	$editLang = $_REQUEST['choose_lang'];
	$gBitSmarty->assign( 'editLang', $editLang );
	// what strings do we want to display?
	if( empty( $_REQUEST['char'] ) ) {
		$pattern = "/^a/i";
	} elseif ( $_REQUEST['char'] == '0-9' ) {
		$pattern = "/^\d/";
	} elseif ( $_REQUEST['char'] == '+' ) {
		$pattern = "/^[^a-zA-Z]/";
	} elseif ( $_REQUEST['char'] == 'all' ) {
		$pattern = null;
	} else {
		$pattern = "/^".$_REQUEST['char']."/i";
	}
	$gBitLanguage->loadLanguage( $editLang );
	$tranStr = $gBitLanguage->mStrings[$editLang];

	foreach( $tranStr as $key => $tran ) {
		// display only the wanted strings and apply a textbox if the string is too long
		if( !empty( $_REQUEST['un_trans'] ) && empty( $tran['trans'] ) || empty( $_REQUEST['un_trans'] ) ) {
			if( empty( $pattern ) || preg_match( $pattern, $tran['source'] ) ) {
				$tranStrings[$key] = $tran;
				if( strlen( $tran['source'] ) > 50 ) {
					$tranStrings[$key]['textarea'] = true;
				}
			}
		}
	}
	$gBitSmarty->assign( 'char', empty( $_REQUEST['char'] ) ? '' : $_REQUEST['char'] );
	$gBitSmarty->assign( 'tranStrings', $tranStrings );
}

$gBitSystem->display( 'bitpackage:languages/translate_strings.tpl', KernelTools::tra( 'Edit Translations' ) , [ 'display_mode' => 'edit' ]);

