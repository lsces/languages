{strip}
{if $gBitUser->hasPermission( 'p_languages_edit' )}
{if $packageMenuTitle}<a class="dropdown-toggle" data-toggle="dropdown" href="#"> {tr}{$packageMenuTitle}{/tr} <b class="caret"></b></a>{/if}
<ul class="{$packageMenuClass}">
	<li><a class="item" href="{$smarty.const.LANGUAGES_PKG_URL}edit_languages.php">{biticon ipackage="icons" iname="emblem-important" iexplain="Edit Languages"}</a></li>
	<li><a class="item" href="{$smarty.const.LANGUAGES_PKG_URL}translate_strings.php">{biticon ipackage="icons" iname="config-language" iexplain="Translate Strings"}</a></li>
	<li><a class="item" href="{$smarty.const.LANGUAGES_PKG_URL}import.php">{biticon ipackage="icons" iname="edit-clear" iexplain="Import / Export"}</a></li>
	<li><a class="item" href="{$smarty.const.LANGUAGES_PKG_URL}master_strings.php">{biticon ipackage="icons" iname="text-x-generic" iexplain="Master Strings"}</a></li>
</ul>
{/if}
{/strip}
