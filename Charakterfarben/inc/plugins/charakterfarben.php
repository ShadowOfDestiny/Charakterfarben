<?php
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

// === FINALE VERSION - KOMBINATION ALLER FUNKTIONIERENDEN TEILE ===

// Hooks
$plugins->add_hook("postbit", "charakterfarben_generate_css_for_post"); // *** NEUER, ZUVERLÄSSIGER HOOK ***
$plugins->add_hook("usercp_start", "charakterfarben_usercp_page");
$plugins->add_hook('usercp_menu', 'charakterfarben_nav', 90);

// *** NUR DIESE ZWEI ZEILEN HINZUFÜGEN ***
$plugins->add_hook("newthread_start", "charakterfarben_preview_css");
$plugins->add_hook("newreply_start", "charakterfarben_preview_css");
    // *** ENDE HINZUFÜGEN ***

function charakterfarben_info()
{
	global $lang;
	$lang->load('charakterfarben');
	return array(
		"name"			=> "Charakterfarben", 
		"description"	=> "Dynamische Farben für wörtliche Rede. Nutzt Datenbank-Templates und direkte CSS-Injektion.",
		"website"		=> "https://shadow.or.at/index.php", 
		"author"		=> "Dani",
		"authorsite"	=> "https://github.com/ShadowOfDestiny", 
		"version"		=> "1.0",
		"compatibility" => "18*"
	);
}

function charakterfarben_install()
{
    global $db, $lang;
	$lang->load('charakterfarben');
    if (!$db->table_exists("charakterfarben")) {
        $db->write_query("CREATE TABLE ".TABLE_PREFIX."charakterfarben (`uid` int(10) NOT NULL, `tid` int(10) NOT NULL, `color` varchar(7) NOT NULL, PRIMARY KEY (`uid`, `tid`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
	$setting_group = ['name' => 'charakterfarben', 
	'title' => 'Charakterfarben Einstellungen', 
	'description' => 'Einstellungen für das Charakterfarben Plugin.', 
	'disporder' => 5, 
	'isdefault' => 0];
	$gid = $db->insert_query("settinggroups", $setting_group);
	$setting = ['name' => 'charakterfarben_fid', 
	'title' => 'Profilfeld-ID für Charakternamen', 
	'description' => 'Gib die ID des Profilfeldes für den Charakternamen an.', 
	'optionscode' => 'numeric', 
	'value' => '8', 
	'disporder' => 1, 'gid' => (int)$gid];
	$db->insert_query("settings", $setting);
    rebuild_settings();
}

function charakterfarben_is_installed()
{
	global $mybb;
	return isset($mybb->settings['charakterfarben_fid']);
}

function charakterfarben_uninstall()
{
	global $db;
    if ($db->table_exists("charakterfarben")) {
        $db->drop_table("charakterfarben");
    }
	$db->delete_query('settings', "name LIKE 'charakterfarben_%'");
	$db->delete_query('settinggroups', "name = 'charakterfarben'");
    rebuild_settings();
	$db->delete_query("templates", "title LIKE '%charakterfarben%'");
}

function charakterfarben_activate()
{
	global $db, $lang;
	
	$lang->load('charakterfarben');
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	
	// Templates für die UCP-Seite erstellen
	$insert_array = array(
		'title'		=> 'charakterfarben_ucp_page',
		'template'	=> $db->escape_string('<html>
<head>
    <title>{$lang->user_cp}</title>
    {$headerinclude}
</head>
<body>
    {$header}
    <table width="100%" border="0" align="center">
        <tr>
            {$usercpnav}
            <td valign="top">
                <form method="post" action="usercp.php">
				<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
                    <table border="0" cellspacing="1" cellpadding="4" class="tborder">
                        <thead>
                            <tr><th class="thead" colspan="3"><strong>{$lang->charakterfarben_ucp_title}</strong></th></tr>
                        </thead>
                        <tbody>
                            <tr><td class="trow2" colspan="3">{$lang->charakterfarben_ucp_desc}</td></tr>
                            <tr>
                                <td class="tcat" width="25%">{$lang->charakterfarben_ucp_theme}</td>
                                <td class="tcat" width="15%">{$lang->charakterfarben_ucp_color}</td>
                                <td class="tcat" width="60%">{$lang->charakterfarben_ucp_preview}</td>
                            </tr>
                            {$style_bit}
                        </tbody>
                    </table>
                    <br />
					<div align="center">
					<input type="hidden" name="action" value="do_charakterfarben" />
                    <input type="submit" class="button" name="charakterfarben"  value="{$lang->charakterfarben_ucp_save}" />
					</div>
                </form>
                {$live_preview_js}
            </td>
        </tr>
    </table>
    {$footer}
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'charakterfarben_ucp_row',
		'template'	=> $db->escape_string('<tr>
    <td class="trow1"><strong>{$style[\'name\']}</strong></td>
    <td class="trow1" style="vertical-align: middle;">
        <input type="color" name="colors[{$style[\'tid\']}]" value="{$saved_color}" id="color_picker_{$style[\'tid\']}" data-preview-id="preview_{$style[\'tid\']}">
    </td>
    <td class="trow1">
        <div id="preview_{$style[\'tid\']}" style="background-color: {$preview_bg_color}; color: {$saved_color}; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
            <strong>{$charakter_name}:</strong> "So sieht die wörtliche Rede aus."
        </div>
    </td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'charakterfarben_nav_usercp',
		'template'	=> $db->escape_string('<tr><td class="trow1"><a href="usercp.php?action=charakterfarben" class="usercp_nav_item">{$lang->charakterfarben_ucp_nav}</a></td></tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);
}

function charakterfarben_deactivate()
{
	global $db, $lang;
	
    $lang->load('charakterfarben');
	include MYBB_ROOT."/inc/adminfunctions_templates.php";

    $db->delete_query("templates", "title LIKE 'charakterfarben%'");
}

// Stabile CSS-Funktion
function charakterfarben_generate_css_for_post(&$post)
{
    global $mybb, $db, $headerinclude;
    static $processed_users = []; // Verhindert, dass der CSS-Code für denselben User mehrfach eingefügt wird

    $user_id = (int)$post['uid'];

    // Wenn für diesen User schon CSS generiert wurde, überspringen
    if (isset($processed_users[$user_id])) {
        return $post;
    }

    $fid = (int)$mybb->settings['charakterfarben_fid'];
    if (!$fid) return $post;

    // Wir lesen den Charakternamen direkt aus den Post-Daten, das ist am sichersten
    $charakter_name = strtolower(trim($post['fid'.$fid]));
    if (empty($charakter_name)) return $post;

    $clean_name = preg_replace('/[^a-z0-9]/', '', $charakter_name);
    if (empty($clean_name)) return $post;

    // Wir brauchen die Farbe für das Theme des Betrachters, nicht des Autors
    $current_theme_id = (int)$mybb->user['style'];
    $query = $db->simple_select("charakterfarben", "color", "uid = '{$user_id}' AND tid = '{$current_theme_id}'", ['limit' => 1]);
    $color_data = $db->fetch_array($query);

    if ($color_data && !empty($color_data['color'])) {
        $headerinclude .= "\n<style type=\"text/css\">.post_body {$clean_name} { color: {$color_data['color']} !important; }</style>\n";
        $processed_users[$user_id] = true; // User als "bearbeitet" markieren
    }

    return $post;
}

function charakterfarben_usercp_page()
{
    global $db, $lang, $mybb, $templates, $header, $headerinclude, $footer, $usercpnav;
    
    if($mybb->input['action'] == "charakterfarben") {
        add_breadcrumb($lang->charakterfarben_ucp_nav, "usercp.php?action=charakterfarben");
		
		// *** NEUER TEIL START ***
        // Charakternamen aus dem Profilfeld des aktuellen Users holen
        $fid = (int)$mybb->settings['charakterfarben_fid'];
        $charakter_name = ""; // Standardwert
        if ($fid > 0 && isset($mybb->user['fid'.$fid])) {
            $charakter_name = htmlspecialchars_uni(trim($mybb->user['fid'.$fid]));
        }
        // Fallback, falls kein Name eingetragen ist
        if (empty($charakter_name)) {
            $charakter_name = "Dein Charaktername";
        }
        // *** NEUER TEIL ENDE ***
		
        $style_bit = '';
        $query = $db->simple_select("themes", "tid, name", "allowedgroups='all' OR FIND_IN_SET({$mybb->user['usergroup']}, allowedgroups)", ['order_by' => 'name']);
        
        while ($style = $db->fetch_array($query)) {
            $color_query = $db->simple_select("charakterfarben", "color", "uid='{$mybb->user['uid']}' AND tid='{$style['tid']}'");
            $saved_color = $db->fetch_field($color_query, "color") ?: '#000000';
            $preview_bg_color = charakterfarben_get_theme_background($style['tid']);
            eval("\$style_bit .= \"".$templates->get("charakterfarben_ucp_row")."\";");
        }

        $live_preview_js = "<script>document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('input[type=\"color\"]').forEach(function(p){p.addEventListener('input',function(e){var i=e.target.dataset.previewId,t=document.getElementById(i);t&&(t.style.color=e.target.value)})})});</script>";
        eval("\$page = \"".$templates->get("charakterfarben_ucp_page")."\";");
        output_page($page);
    }

    if($mybb->input['action'] == "do_charakterfarben") {
        verify_post_check($mybb->input['my_post_key']);
        if ($mybb->request_method == 'post') {
            foreach ($mybb->input['colors'] as $tid => $color) {
                $tid = (int)$tid;
                if (preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color)) {
                    $db->replace_query("charakterfarben", ['uid' => $mybb->user['uid'], 'tid' => $tid, 'color' => $db->escape_string($color)]);
                }
            }
        }
        redirect("usercp.php?action=charakterfarben", $lang->charakterfarben_ucp_success);
    }
}

/**
 * Fügt das CSS für den aktuellen User speziell für die Beitragsvorschau hinzu.
 */
function charakterfarben_preview_css()
{
    global $mybb, $db, $headerinclude;

    // Nur ausführen, wenn der "Vorschau"-Button geklickt wurde
    if (isset($mybb->input['previewpost'])) 
    {
        if ($mybb->user['uid'] == 0) return;

        $fid = (int)$mybb->settings['charakterfarben_fid'];
        if (!$fid) return;

        $charakter_name = strtolower(trim($mybb->user['fid'.$fid]));
        if (empty($charakter_name)) return;

        $clean_name = preg_replace('/[^a-z0-9]/', '', $charakter_name);
        if (empty($clean_name)) return;

        $current_theme_id = (int)$mybb->user['style'];
        $query = $db->simple_select("charakterfarben", "color", "uid = '{$mybb->user['uid']}' AND tid = '{$current_theme_id}'", ['limit' => 1]);
        $color_data = $db->fetch_array($query);

        if ($color_data && !empty($color_data['color'])) {
            $headerinclude .= "\n<style type=\"text/css\">.post_body {$clean_name} { color: {$color_data['color']} !important; }</style>\n";
        }
    }
}

function charakterfarben_get_theme_background($tid) {
    global $db; $tid = (int)$tid; $query = $db->simple_select("themestylesheets", "stylesheet", "tid='{$tid}' AND name IN ('global.css', 'normalize.css')", ['order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1]);
    $stylesheet = $db->fetch_field($query, "stylesheet"); if ($stylesheet && preg_match('/body\s*{[^}]*background(-color)?:\s*([^;}\s]+)/i', $stylesheet, $matches)) { return $matches[2]; } return '#282c34';
}

function charakterfarben_nav() {
	global $mybb, $templates, $lang, $usercpmenu;
	$lang->load("charakterfarben");
	eval("\$usercpmenu .= \"".$templates->get("charakterfarben_nav_usercp")."\";");
}

?>