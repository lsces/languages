<?php
/**
 * @package languages
 * @version $Header$
 *
 * Copyright (c) 2005 bitweaver.org
 * Copyright (c) 2004-2005, Christian Fowler, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 * @author spider <spider@steelsun.com>
 */

/**
 * @package languages
 */

namespace Bitweaver\Languages;

use Bitweaver\BitSingleton;
use Bitweaver\KernelTools;

class BitLanguage extends BitSingleton {
	// list of available (non-disabled) languages
	public $mLanguageList;

	public $mLanguage;

	public $mStrings;

	public $mImportConflicts;

	/**
	 * initiate BitLanguage 
	 */
	function __construct() {
		parent::__construct();
		$this->load();
	}

	public function load() {
		if( parent::load() && !defined( 'BIT_INSTALL' ) ) {
			$this->mLanguageList = $this->listLanguages();
			$this->autoSetLanguage();
		}
	}

	protected function autoSetLanguage() {
		global $gBitSystem;

		if (isset($_SESSION['bitlanguage'])) {
			// users not logged that change the preference
			$this->setLanguage( $_SESSION['bitlanguage'] );
		} elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($gBitSystem) && $gBitSystem->isFeatureActive( 'i18n_browser_languages' )) {
			// Get supported languages
			if( $browserLangs = preg_split( '/,/', preg_replace('/;q=[0-9.]+/', '', $_SERVER['HTTP_ACCEPT_LANGUAGE']) ) ) {
				foreach( $browserLangs as $bl ) {
					if( !empty( $this->mLanguageList[$bl] ) ) {
						$this->setLanguage( $bl );
						break;
					} elseif( strpos( $bl, '-' ) )  {
						$baseLang = substr( $bl, 0, 2 );
						if( !empty( $this->mLanguageList[$baseLang] ) ) {
							$this->setLanguage( $baseLang );
							break;
						}
					}
				}
			}
		}
		if( empty( $this->mLanguage ) ) {
			$this->setLanguage( $gBitSystem->getConfig('bitlanguage', 'en') );
		}
	}

	public function isCacheableObject() {
		return !empty( $this->mLanguageList );
	}

	public function __wakeup() {
		parent::__wakeup();
		if( empty( $this->mLanguageList ) ) {
			$this->load();
		}
	}

	public function __sleep() {
		return array_merge( parent::__sleep(), [ 'mLanguageList' ] );
	}

	/**
	 * getLanguage get acvtive language
	 * 
	 * @return string active language
	 */
	public function getLanguage() {
		return $this->mLanguage;
	}

	/**
	 * setLanguage set active language
	 * 
	 * @param string $pLangCode Language code
	 * @access public
	 * @return void
	 */
	function setLanguage( $pLangCode ) {
		$this->mLanguage = $pLangCode;
	}

	function isLanguageRTL () {
		return !empty( $this->mLanguageList[$this->mLanguage]['right_to_left'] );
	}

	/**
	 * verifyLanguage verify language hash before storing it
	 * 
	 * @param array $pParamHash parameters that will be stored
	 * @access public
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	function verifyLanguage( &$pParamHash ) {
		$langs = $this->listLanguages();
		if( empty( $pParamHash['lang_code'] ) || strlen( $pParamHash['lang_code'] ) < 2 ) {
			$this->mErrors['lang_code'] = KernelTools::tra( 'The language code must be at least 2 characters.' );
		} elseif( !empty( $langs[$pParamHash['lang_code']] ) && empty( $pParamHash['update_lang_code'] ) ) {
			$this->mErrors['lang_code'] = KernelTools::tra( 'This language code is already used by ' ).$langs[$pParamHash['lang_code']]['native_name'];
		}
		if( empty( $pParamHash['native_name'] ) ) {
			$this->mErrors['native_name'] = 'You must provide the native language name';
		}
		if( !isset( $pParamHash['english_name'] ) ) {
			$pParamHash['english_name'] = null;
		}
		$pParamHash['is_disabled'] = !empty( $pParamHash['is_disabled'] ) ? 'y' : null;
		return count( $this->mErrors ) === 0;
	}

	/**
	 * storeLanguage store language in database
	 * 
	 * @param array $pParamHash parameters that will be stored
	 * @access public
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	function storeLanguage( $pParamHash ) {
		if( $this->verifyLanguage( $pParamHash ) ) {
			if( empty( $pParamHash['update_lang_code'] ) ) {
				$query = "INSERT INTO `".BIT_DB_PREFIX."i18n_languages` (`lang_code`,`english_name`,`native_name`,`is_disabled`) values (?,?,?,?)";
				$result = $this->mDb->query( $query, [ $pParamHash['lang_code'], $pParamHash['english_name'], $pParamHash['native_name'], $pParamHash['is_disabled'] ] );
			} else {
				$query = "UPDATE `".BIT_DB_PREFIX."i18n_languages` SET `lang_code`=?, `english_name`=?, `native_name`=?, `is_disabled`=? WHERE `lang_code`=?";
				$result = $this->mDb->query( $query, [ $pParamHash['lang_code'], $pParamHash['english_name'], $pParamHash['native_name'], $pParamHash['is_disabled'], $pParamHash['update_lang_code'] ] );
			}
		}
		return count( $this->mErrors ) == 0;
	}

	/**
	 * expungeLanguage remove language from database
	 * 
	 * @param string $pLangCode Language code
	 * @access public
	 * @return void
	 */
	function expungeLanguage( $pLangCode ) {
		if( !empty( $pLangCode ) ) {
			$this->StartTrans();
			$query = "DELETE FROM `".BIT_DB_PREFIX."i18n_strings` WHERE `lang_code`=?";
			$result = $this->mDb->query( $query, [ $pLangCode ] );
			$query = "DELETE FROM `".BIT_DB_PREFIX."i18n_languages` WHERE `lang_code`=?";
			$result = $this->mDb->query( $query, [ $pLangCode ] );
			$this->CompleteTrans();
		}
	}

	/**
	 * expungeMasterString remove master string from database
	 * 
	 * @param string $pSourceHash MD5 hash of master string
	 * @return void
	 */
	public function expungeMasterString( $pSourceHash ) {
		if( !empty( $pSourceHash ) ) {
			$this->StartTrans();
			$query = "DELETE FROM `".BIT_DB_PREFIX."i18n_strings` WHERE `source_hash`=?";
			$result = $this->mDb->query( $query, [ $pSourceHash ] );
			$query = "DELETE FROM `".BIT_DB_PREFIX."i18n_masters` WHERE `source_hash`=?";
			$result = $this->mDb->query( $query, [ $pSourceHash ] );
			$this->CompleteTrans();
		}
	}

	/**
	 * getImportedLanguages get a list of languages that have been imported
	 * 
	 * @return array of available languages
	 */
	public function getImportedLanguages() {
		$ret = [];
		if( $rs = $this->mDb->query( 'SELECT DISTINCT(`lang_code`) AS `lang_code` FROM `'.BIT_DB_PREFIX.'i18n_strings`' ) ) {
			$res = [];
			while( !$rs->EOF ) {
				$res[] = $rs->fields['lang_code'];
				$rs->MoveNext();
			}
			$langs = $this->listLanguages();
			foreach( $res as $langCode ) {
				$ret[$langCode] = $langs[$langCode];
			}
		}
		return $ret;
	}

	/**
	 * listLanguages list languages
	 * 
	 * @param boolean $pListDisabled 
	 * @param boolean $pListOnlyImportable 
	 * @return array of languages
	 */
	public function listLanguages( $pListDisabled=true, $pListOnlyImportable=false ) {
		$whereSql = '';
		$langs = [];
		if( !$pListDisabled ) {
			$whereSql = " WHERE `is_disabled` IS null ";
		}
		$ret = $this->mDb->getAssoc( "SELECT il.`lang_code` AS `hash_key`, il.* FROM `".BIT_DB_PREFIX."i18n_languages` il $whereSql ORDER BY il.`lang_code`" );
		if( !empty( $ret ) ) {
			foreach( array_keys( $ret ) as $langCode ) {
				if( $langCode != 'en' && !$this->isImportFileAvailable( $langCode ) && $pListOnlyImportable )
					continue;
				$ret[$langCode]['translated_name'] = $this->translate( $ret[$langCode]['english_name'] );
				$ret[$langCode]['full_name'] = $ret[$langCode]['native_name'].' ('.$this->translate( $ret[$langCode]['english_name'] ).', '.$langCode.')';
				$langs[$langCode] = $ret[$langCode];
			}
		}
		return $langs;
	}

	/**
	 * verifyMastersLoaded verify that master strings are loaded
	 * 
	 * @return void
	 */
	public function verifyMastersLoaded() {
		// see if there is anything in the table
		$query = "SELECT COUNT(`source_hash`) FROM `".BIT_DB_PREFIX."i18n_masters`";
		$count = $this->mDb->getOne( $query );
		if( empty( $count )) {
			$this->importMasterStrings();
		}
	}

	/**
	 * masterStringExists check to see if a given master string already exists
	 * 
	 * @param string $pSourceHash MD5 hash of string to be checked
	 * @return bool true if found, false otherwise
	 */
	public function masterStringExists( $pSourceHash ) {
		return !empty( $this->mStrings['master'][$pSourceHash] );
	}

	/**
	 * searchMasterStrings find master string in database
	 * 
	 * @param string $pQuerySource string
	 * @return bool true on success, false on failure
	 */
	public function searchMasterStrings( $pQuerySource ) {
		$query = "
			SELECT im.`source_hash` AS `hash_key`, `source`, `package`, im.`source_hash`
			FROM `".BIT_DB_PREFIX."i18n_masters` im
			WHERE UPPER( `source` ) LIKE ? ORDER BY im.`source`";
		return $this->mDb->getAssoc( $query, [ '%'.strtoupper( $pQuerySource ).'%' ] );
	}

	/**
	 * loadMasterStrings load all master strings
	 * 
	 * @param string $pSourceHash MD5 hash to load
	 * @param string $pFilter Limit strings loaded to unlimited (default), translated or untranslated
	 * @return void all master strings in $this->mStrings['master']
	 */
	public function loadMasterStrings( $pSourceHash = null, $pFilter = null, $pLangCode = null ) {
		$this->verifyMastersLoaded();
		$bindVars = null;
		$whereSql = $joinSql = null;

		if( $pSourceHash ) {
			$whereSql = ' WHERE `source_hash`=? ';
			$bindVars = [ $pSourceHash ];
		} else {
			// some basic filter options
			if( !empty( $pFilter )) {
				$joinSql = "LEFT OUTER JOIN `".BIT_DB_PREFIX."i18n_strings` ist ON( im.`source_hash` = ist.`source_hash` )";
				if( $pFilter == 'translated' ) {
					$whereSql = "WHERE ist.`trans` IS NOT null";
					if( !empty( $pLangCode )) {
						$whereSql .= " AND ist.`lang_code` = ?";
						$bindVars[] = $pLangCode;
					}
				} elseif( $pFilter == 'untranslated' ) {
					$whereSql = "WHERE ist.`trans` IS null";
					// can't work out SQL to do language limits in this filter
				}
			}
		}

		$query = "
			SELECT im.`source_hash` AS `hash_key`, `source`, `package`, im.`source_hash`
			FROM `".BIT_DB_PREFIX."i18n_masters` im
			$joinSql $whereSql ORDER BY im.`source`";
		$this->mStrings['master'] = $this->mDb->getAssoc( $query, $bindVars );
	}

	/**
	 * storeMasterString store master string
	 * 
	 * @param array $pParamHash data to be stored
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	public function storeMasterString( $pParamHash ) {
		global $gBitSmarty;
		if( !empty( $gBitSmarty->mCompileRsrc ) ) {
			list($type, $location) = explode( ':', $gBitSmarty->mCompileRsrc );
			list($package, $file) = explode( '/', $location );
		} else {
			$package = null;
		}

		$this->StartTrans();
		$newSourceHash = $this->getSourceHash( $pParamHash['new_source'] );
		if( $this->masterStringExists( $newSourceHash ) ) {
			$oldCount = $this->mDb->getOne( "SELECT COUNT(`source_hash`) FROM `".BIT_DB_PREFIX."i18n_strings` WHERE `source_hash`=?",  [ $pParamHash['source_hash'] ] );
			$newCount = $this->mDb->getOne( "SELECT COUNT(`source_hash`) FROM `".BIT_DB_PREFIX."i18n_strings` WHERE `source_hash`=?",  [ $newSourceHash ] );
			if( $newCount ) {
				$this->mErrors['master'] = 'There was a conflict updating the master string. The new string already has translations entered.';
			} else {
				// we have updated a master string to an existing master string
				$query = "UPDATE `".BIT_DB_PREFIX."i18n_strings` SET `source_hash`=?, `last_modified`=? WHERE `source_hash`=?";
				$trans = $this->mDb->query($query, [ $newSourceHash, time(), $pParamHash['source_hash'] ] );
				$query = "DELETE FROM `".BIT_DB_PREFIX."i18n_masters` WHERE `source_hash`=?";
				$trans = $this->mDb->query($query, [ $pParamHash['source_hash'] ] );
			}
		} elseif( $this->masterStringExists( $pParamHash['source_hash'] ) ) {
			$query = "UPDATE `".BIT_DB_PREFIX."i18n_strings` SET `source_hash`=?, `last_modified`=? WHERE `source_hash`=?";
			$trans = $this->mDb->query($query, [ $newSourceHash, time(), $pParamHash['source_hash'] ] );
			$query = "UPDATE `".BIT_DB_PREFIX."i18n_masters` SET `source_hash`=?, `source`=?, `created`=? WHERE `source_hash`=?";
			$trans = $this->mDb->query($query, [ $newSourceHash, $pParamHash['new_source'], time(), $pParamHash['source_hash'] ] );
			unset( $this->mStrings[$pParamHash['source_hash']] );
		} else {
			$query = "INSERT INTO `".BIT_DB_PREFIX."i18n_masters` (`source`,`source_hash`, `created`, `package`) VALUES (?,?,?,?)";
			$trans = $this->mDb->query($query, [ $pParamHash['new_source'], $newSourceHash, time(), $package ] );
		}
		if( count( $this->mErrors ) == 0 ) {
			$this->mStrings['master'][$newSourceHash]['source'] = $pParamHash['new_source'];
			$this->mStrings['master'][$newSourceHash]['source_hash'] = $newSourceHash;
		}
		$this->CompleteTrans();
		return count( $this->mErrors ) == 0;
	}

	/**
	 * importMasterStrings 
	 * 
	 * @param boolean $pOverwrite 
	 * @return bool true on success, false on failure
	 */
	public function importMasterStrings( $pOverwrite=false ) {
		global $lang;
		$count = 0;
		include_once LANGUAGES_PKG_PATH.'lang/masters.php';

		foreach( $lang as $key=>$val ) {
			$sourceHash = $this->getSourceHash( $key );
			$query = "SELECT * FROM `".BIT_DB_PREFIX."i18n_masters` WHERE `source_hash`=?";
			$trans = $this->mDb->getAssoc($query, [ $sourceHash ] );
			if( $trans ) {
				if( $pOverwrite ) {
					$query = "UPDATE `".BIT_DB_PREFIX."i18n_masters` SET `source`=?, `created`=? WHERE `source_hash`=?";
					$trans = $this->mDb->query($query, [ $val, time(), $sourceHash ] );
					$count++;
				}
			} else {
				$this->storeMasterString( [ 'new_source' => $val, 'source_hash' => $sourceHash ] );
				$count++;
			}
		}
		return $count;
	}

	/**
	 * storeTranslationString 
	 * 
	 * @param string $pLangCode Language code
	 * @param string $pString 
	 * @param string $pSourceHash MD5 hash of master string
	 * @return void
	 */
	public function storeTranslationString( $pLangCode, $pString, $pSourceHash ) {
		$query = "DELETE FROM `".BIT_DB_PREFIX."i18n_strings` WHERE `source_hash`=? AND `lang_code`=?";
		$result = $this->mDb->query( $query, [ $pSourceHash, $pLangCode ] );

		// we don't need things where '{$menu.menu_title}' is the full string in the database
		// if you change this regexp, please modify the one in kernel/smarty_bit/prefilter.tr.php as well (approx line 76)
		if( !empty( $pString ) && !preg_match( '!^(\{\$[^\}]*\})+$!', $pString ) ) {
			$query = "INSERT INTO `".BIT_DB_PREFIX."i18n_strings` (`lang_code`,`trans`,`source_hash`, `last_modified`) values (?,?,?,?)";
			$result = $this->mDb->query( $query, [ $pLangCode, $pString, $pSourceHash, time() ] );
		}
		// pretty brutal on mass-saving, but cache always needs purging after translation saved.
		$this->clearCache();
		$this->mStrings[$pLangCode][$pSourceHash]['trans'] = $pString;
	}

	/**
	 * getTranslatedStrings 
	 * 
	 * @param string $pSourceHash MD5 hash of master string
	 * @return array of translated strings
	 */
	public function getTranslatedStrings( $pSourceHash ) {
		$query = "
			SELECT ist.`lang_code` AS `hash_key`, `trans`, ist.`source_hash`, ist.`lang_code`
			FROM `".BIT_DB_PREFIX."i18n_strings` ist
			WHERE ist.`source_hash`=?
			ORDER BY ist.`lang_code`";
		return $this->mDb->getAssoc( $query, [ $pSourceHash ] );
	}

	/**
	 * getTranslationString 
	 * 
	 * @param string $pSourceHash MD5 hash of master string
	 * @param string $pLangCode Language code
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	public function getTranslationString( $pSourceHash, $pLangCode ) {
		$this->verifyTranslationLoaded( $pLangCode );
		$query = "
			SELECT im.`source_hash` AS `hash_key`, `source`, `trans`, im.`source_hash`
			FROM `".BIT_DB_PREFIX."i18n_masters` im
				LEFT OUTER JOIN `".BIT_DB_PREFIX."i18n_strings` ist ON( ist.`source_hash`=im.`source_hash` AND ist.`lang_code`=? )
			WHERE im.`source_hash`=?
			ORDER BY im.`source`";
		return $this->mDb->getAssoc( $query, [ $pLangCode, $pSourceHash ] );
	}

	/**
	 * getLanguageFile 
	 * 
	 * @param string $pLangCode Language code
	 * @access public
	 * @return string
	 */
	public function getLanguageFile( $pLangCode ) {
		return LANGUAGES_PKG_PATH.'lang/'.$pLangCode.'/language.php';
	}

	/**
	 * isImportFileAvailable 
	 * 
	 * @param string $pLangCode Language code
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	public function isImportFileAvailable( $pLangCode ) {
		return file_exists( $this->getLanguageFile( $pLangCode ) );
	}

	/**
	 * importTranslationStrings 
	 * 
	 * @param string $pLangCode Language code
	 * @param bool $pOverwrite 
	 * @param string $pTable 
	 * @param string $pFile path to file
	 * @return int
	 */
	public function importTranslationStrings( $pLangCode, $pOverwrite=false, $pTable='i18n_strings`', $pFile=false ) {
		$count = 0;

		if( empty( $pFile ) ) {
			if( $this->isImportFileAvailable( $pLangCode ) ) {
				$pFile = $this->getLanguageFile( $pLangCode );
			}
		}

		if( !empty( $pFile ) && file_exists( $pFile ) ) {
			$this->loadMasterStrings();

			// read the file and parse out the master/trans string pairs manually to prevent any evil shit from getting exec'ed
			$handle = fopen( $pFile, "r" );
			$line = '';
			while (!feof($handle)) {
				$line .= fgets( $handle );
				if( preg_match( '/([\'"])(.*?)(?<!\\\\)\1[\n\r\s]*=>[\n\r\s]*([\'"])(.*?)(?<!\\\\)\3/msS', $line, $match )) {
					$lang[stripslashes( $match[2] )] = stripslashes( $match[4] );
					$line = '';
				}
			}
			fclose( $handle );

			foreach( $lang as $key=>$val ) {
				$hashKey = $this->getSourceHash( $key );
				if( !$this->masterStringExists( $hashKey ) ) {
					$this->storeMasterString( [ 'source_hash' => $hashKey, 'new_source' => $key ] );
				}
				$trans = $this->lookupTranslation( $key, $pLangCode, false );
				if( !is_null( $trans ) ) {
					if( $pOverwrite ) {
						$query = "UPDATE `".BIT_DB_PREFIX."i18n_strings` SET `trans`=?, `last_modified`=? WHERE `source_hash`=? AND `lang_code`=?";
						$trans = $this->mDb->query($query, [ $val, time(), $hashKey, $pLangCode ] );
						$count++;
					} elseif( !empty( $val ) && strtolower( $trans ) != strtolower( $val ) ) {
						$this->mImportConflicts[$pLangCode][$hashKey]['import'] = $val;
						$this->mImportConflicts[$pLangCode][$hashKey]['existing'] = $trans;
						if( !empty( $this->mStrings['master'][$hashKey]['source'] ) ) {
							$this->mImportConflicts[$pLangCode][$hashKey]['master'] = $this->mStrings['master'][$hashKey]['source'];
						}
					}
				} elseif( !empty( $val ) && (strtolower( $key ) != strtolower( $val )) ) {
					$query = "INSERT INTO `".BIT_DB_PREFIX."i18n_strings` (`trans`,`source_hash`,`lang_code`,`last_modified`) VALUES (?,?,?,?)";
					$trans = $this->mDb->query($query, [ $val, $hashKey, $pLangCode, time() ] );
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * verifyTranslationLoaded 
	 * 
	 * @param string $pLangCode Language code
	 * @return void
	 */
	public function verifyTranslationLoaded( $pLangCode ) {
		if ( $pLangCode ) {
			// see if there is anything in the table
			$query = "SELECT COUNT(`source_hash`) FROM `".BIT_DB_PREFIX."i18n_strings` ist WHERE ist.`lang_code`=?";
			$count = $this->mDb->getOne($query, [ $pLangCode ] );
			if( empty( $count ) ) {
				$this->importTranslationStrings( $pLangCode );
			}
		}
	}

	/**
	 * loadLanguage 
	 * 
	 * @param string $pLangCode Language code
	 * @return void
	 */
	public function loadLanguage( $pLangCode ) {
		$this->verifyMastersLoaded();
		$this->verifyTranslationLoaded( $pLangCode );
		$query = "
			SELECT im.`source_hash` AS `hash_key`, `source`, `trans`, im.`source_hash`, ivm.`version`
			FROM `".BIT_DB_PREFIX."i18n_masters` im
				LEFT OUTER JOIN `".BIT_DB_PREFIX."i18n_strings` ist ON( ist.`source_hash`=im.`source_hash` AND ist.`lang_code`=? )
				LEFT OUTER JOIN `".BIT_DB_PREFIX."i18n_version_map` ivm ON( im.`source_hash`=ivm.`source_hash` )
			ORDER BY im.`source`";
		$this->mStrings[$pLangCode] = $this->mDb->getAssoc( $query, [ $pLangCode ] );
	}

	/**
	 * translate 
	 * 
	 * @param string $pString 
	 * @return string translation
	 */
	public function translate( $pString ) {
		global $gBitTranslationHash, $gBitSystem;
		$ret = $pString;
		$sourceHash = $this->getSourceHash( $pString );
		$cacheFile = TEMP_PKG_PATH."lang/".$this->mLanguage."/".$sourceHash;
		if( empty( $this->mLanguage ) || $this->mLanguage === 'en' ) {
			return $ret;
		}
		if( !empty( $this->mStrings[$this->mLanguage][$sourceHash] ) ) {
			$ret = $this->mStrings[$this->mLanguage][$sourceHash]['trans'];
		} elseif( file_exists( $cacheFile ) ) {
			if ( !empty($gBitSystem) && !$gBitSystem->isFeatureActive( 'i18n_interactive_translation' ) ) {
				$ret = file_get_contents( $cacheFile );
			}
		} else {
			if( empty( $this->mStrings[$this->mLanguage] ) ) {
				$this->verifyTranslationLoaded( $this->mLanguage );
			}
			$tran = $this->lookupTranslation( $pString, $this->mLanguage );
			if( empty( $tran ) ) {
				// lookup failed. let's snag the first part of the langCode if it is a dialect (e.g. pt-br )
				$dialect = strpos( $this->mLanguage ?? '', '-' );
				if( $dialect ) {
					$tran = $this->lookupTranslation( $pString, substr( $this->mLanguage, 0, $dialect ) );
				}
				if( empty( $tran ) ) {
					$tran = $pString;
				}
			}
			// write out the cache - translated or not so we don't keep hitting the database
			KernelTools::mkdir_p( dirname( $cacheFile ) );
			$fp = fopen( $cacheFile, 'w' );
			fwrite( $fp, $tran );
			fclose( $fp );
			$this->mStrings[$this->mLanguage][$sourceHash]['trans'] = $tran;
			$ret = $tran;
		}

		// interactive translation process
		if( !empty($gBitSystem) && $gBitSystem->isFeatureActive( 'i18n_interactive_translation' ) ) {
			if( empty( $gBitTranslationHash ) ) {
				$gBitTranslationHash = [];
			}
			if( !$index = array_search( $sourceHash, $gBitTranslationHash ) ) {
				$gBitTranslationHash[] = $sourceHash;
				$index = count( $gBitTranslationHash ) - 1;
			}
			$ret .= '_'.$index;
		}

		return $ret;
	}

	/**
	 * lookupTranslation 
	 * 
	 * @param string $pString 
	 * @param string $pLangCode Language code
	 * @param boolean $pOverrideUsage 
	 * @return bool true on success, false on failure - mErrors will contain reason for failure
	 */
	public function lookupTranslation( $pString, $pLangCode, $pOverrideUsage = true ) {
		global $gBitSystem;
		$sourceHash = $this->getSourceHash( $pString );
		if ( $pLangCode ) {
			$query = "SELECT `trans`, ivm.`version`, ivm.`source_hash` AS `usage_source_hash`
				FROM `".BIT_DB_PREFIX."i18n_masters` im
				LEFT OUTER JOIN `".BIT_DB_PREFIX."i18n_version_map` ivm ON( ivm.`source_hash`=im.`source_hash` AND ivm.`version`=? )
				LEFT OUTER JOIN `".BIT_DB_PREFIX."i18n_strings` ist ON( im.`source_hash`=ist.`source_hash` AND `lang_code`=? )
				WHERE im.`source_hash`=?";
			$ret = $this->mDb->getRow($query, [ BIT_MAJOR_VERSION, $pLangCode, $sourceHash ] );
			if( $pOverrideUsage && $gBitSystem->isFeatureActive( 'i18n_record_untranslated' ) ) {
				$query = "SELECT `source_hash` FROM `".BIT_DB_PREFIX."i18n_masters` WHERE `source_hash`=?";
				$source = $this->mDb->getOne($query, [ $sourceHash ] );
				if( empty( $source ) ) {
					$this->storeMasterString( [ 'source_hash' => $sourceHash, 'new_source' => $pString ] );
				}
			}
			if( $pOverrideUsage && $gBitSystem->isFeatureActive( 'i18n_track_translation_usage' ) ) {
				if( empty( $ret['usage_source_hash'] ) ) {
					$query = "INSERT INTO `".BIT_DB_PREFIX."i18n_version_map` (`source_hash`,`version`) VALUES (?,?)";
					$trans = $this->mDb->query($query, [ $sourceHash, BIT_MAJOR_VERSION ] );
				}
			}
		}
		return $ret['trans'] ?? null;
	}

	/**
	 * getMasterString
	 * 
	 * @param string $pSourceHash
	 * @access public
	 * @return string master string with given source hash
	 */
	public function getMasterString( $pSourceHash ) {
		return $this->mDb->getOne( "SELECT `source` FROM `" . BIT_DB_PREFIX . "i18n_masters` WHERE `source_hash` = ? ", [ $pSourceHash ] );
	}

	/**
	 * getSourceHash 
	 * 
	 * @param string $pString 
	 * @return string MD5 hash of string
	 */
	public function getSourceHash( $pString ) {
		return md5( strtolower( trim( $pString ?? '' )));
	}

	/**
	 * clearCache 
	 * 
	 * @return void
	 */
	public function clearCache() {
		KernelTools::unlink_r( TEMP_PKG_PATH."lang/" );
		KernelTools::unlink_r( TEMP_PKG_PATH."templates_c/" );
	}
}

