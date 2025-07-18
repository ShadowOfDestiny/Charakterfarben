<?php
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

// === FINALE VERSION - KOMBINATION ALLER FUNKTIONIERENDEN TEILE ===

// ### Hooks ###
$plugins->add_hook("postbit", "charakterfarben_post_handler"); // Erledigt CSS & Textumwandlung für fertige Posts
$plugins->add_hook("newreply_start", "charakterfarben_preview_handler"); // Erledigt CSS & Textumwandlung für die Vorschau
$plugins->add_hook("newthread_start", "charakterfarben_preview_handler");

// UCP und ACP Hooks
$plugins->add_hook("usercp_start", "charakterfarben_usercp_page");
$plugins->add_hook('usercp_menu', 'charakterfarben_nav', 90);
$plugins->add_hook("admin_config_menu", "charakterfarben_admin_menu");
$plugins->add_hook("admin_config_action_handler", "charakterfarben_admin_action_handler");
$plugins->add_hook("admin_config_permissions", "charakterfarben_admin_permissions");
$plugins->add_hook("admin_load", "charakterfarben_admin_load");


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
		'disporder' => 1, 
		'gid' => (int)$gid];
	$db->insert_query("settings", $setting);
	
	// NEUE EINSTELLUNG FÜR DIE FORENAUSWAHL
    $setting2 = ['name' => 'charakterfarben_active_forums', 
		'title' => 'Aktive Foren', 
		'description' => 'Wähle die Foren aus, in denen die automatische Einfärbung aktiv sein soll. Wähle keine aus, um es überall zu deaktivieren.', 
		'optionscode' => 'forumselect', 
		'value' => '', 'disporder' => 2, 
		'gid' => (int)$gid];
    $db->insert_query("settings", $setting2);
	
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
	
	// Wir prüfen, ob die Einstellung bereits existiert.
    $query = $db->simple_select("settings", "sid", "name='charakterfarben_active_forums'");
    if($db->num_rows($query) == 0)
    {
        // Wenn nicht, fügen wir sie hinzu.
        $setting_group_query = $db->simple_select("settinggroups", "gid", "name='charakterfarben'");
        $gid = $db->fetch_field($setting_group_query, "gid");

        // KORREKTUR hier: 'forumselect' statt 'forumselect_multiple'
        $setting = ['name' => 'charakterfarben_active_forums', 'title' => 'Aktive Foren', 'description' => 'Wähle die Foren aus, in denen die automatische Einfärbung aktiv sein soll. Wähle keine aus, um es überall zu deaktivieren.', 'optionscode' => 'forumselect', 'value' => '', 'disporder' => 2, 'gid' => (int)$gid];
        $db->insert_query("settings", $setting);
        rebuild_settings();
    }

	
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
            Dein Charaktername: "So sieht die wörtliche Rede aus."
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

// ### Kernfunktionen ###

/**
 * DIESE FUNKTION ERSETZT DEINE "charakterfarben_auto_format"
 * Sie ist jetzt die einzige Funktion, die den Text umwandelt.
 */
function charakterfarben_post_handler(&$post)
{
    global $mybb, $db, $headerinclude;
    static $processed_users_css = [];

    // ### TÜRSTEHER ###
    $active_forums = explode(',', $mybb->settings['charakterfarben_active_forums']);
    if (!in_array($post['fid'], $active_forums)) {
        return $post;
    }

    // --- Teil A: CSS hinzufügen (unverändert, funktioniert) ---
    $user_id = (int)$post['uid'];
    if (!isset($processed_users_css[$user_id]) && $user_id > 0) {
        $fid = (int)$mybb->settings['charakterfarben_fid'];
        if ($fid && isset($post['fid'.$fid])) {
            $char_name_css = strtolower(trim($post['fid'.$fid]));
            $clean_name_css = preg_replace('/[^a-z0-9]/', '', $char_name_css);
            if (!empty($clean_name_css)) {
                $current_theme_id = (int)$mybb->user['style'];
                if ($mybb->user['style'] == 0) { // Fallback für Admins/Gäste ohne explizites Theme
                    $current_theme_id = 1; // oder eine andere Standard-Theme-ID
                }
                $query = $db->simple_select("charakterfarben", "color", "uid = '{$user_id}' AND tid = '{$current_theme_id}'", ['limit' => 1]);
                $color_data = $db->fetch_array($query);
                if ($color_data && !empty($color_data['color'])) {
                    $headerinclude .= "\n<style type=\"text/css\">.post_body span.{$clean_name_css} { color: {$color_data['color']} !important; }</style>\n";
                    $processed_users_css[$user_id] = true;
                }
            }
        }
    }

    // --- Teil B: Nachrichtentext umwandeln (FINALE, KORRIGIERTE LOGIK) ---
    $fid = (int)$mybb->settings['charakterfarben_fid'];
    if (isset($post['uid']) && $post['uid'] > 0 && isset($post['fid'.$fid])) {
        $char_name = strtolower(trim($post['fid'.$fid]));
        $clean_name = preg_replace('/[^a-z0-9]/', '', $char_name);

        if (!empty($clean_name) && !strpos($post['message'], "class=\"{$clean_name}\"")) {
            
            $original_message = $post['message'];

            // ### START DER NEUEN LOGIK ###
            
            // Der Regex, der über Zeilenumbrüche hinweg sucht
            $regex = '/("[\s\S]*?")|(„[\s\S]*?“)|(“[\s\S]*?”)|(«[\s\S]*?»)|(»[\s\S]*?«)/u';

            $new_message = preg_replace_callback(
                $regex,
                function ($matches) use ($clean_name) {
                    $quote = $matches[0];
                    
                    // INTelligente Sicherheitsprüfung:
                    // 1. Wir entfernen temporär die erlaubten <br>-Tags für die Prüfung.
                    $check_string = str_ireplace(['<br>', '<br />'], '', $quote);
                    
                    // 2. Wir entfernen die äußeren Anführungszeichen für die Prüfung.
                    $check_string = trim($check_string, '"„“«»');
                    
                    // 3. Jetzt prüfen wir, ob *andere* HTML-Tags oder MyCode im Zitat enthalten sind.
                    if (preg_match('/<[a-z]/i', $check_string) || strpos($check_string, '[') !== false) {
                        // Zitat enthält komplexes HTML (z.B. Links) oder MyCode -> nicht anfassen
                        return $quote;
                    }
                    
                    // Das Zitat ist sicher, wir färben es ein.
                    return '<span class="'.$clean_name.'">' . $quote . '</span>';
                },
                $original_message
            );
            
            // ### ENDE DER NEUEN LOGIK ###

            // Sicherheitsprüfung: Nur übernehmen, wenn die Änderung erfolgreich war.
            if ($new_message !== null && !empty(trim($new_message))) {
                $post['message'] = $new_message;
            } else {
                $post['message'] = $original_message;
            }
        }
    }
    
    return $post;
}

function charakterfarben_preview_handler()
{
    global $mybb, $db, $headerinclude;

    if (isset($mybb->input['previewpost'])) 
    {
        // Türsteher für aktive Foren
        $active_forums = explode(',', $mybb->settings['charakterfarben_active_forums']);
        if (!empty($active_forums[0]) && isset($mybb->input['fid'])) {
            $current_fid = $mybb->get_input('fid', MyBB::INPUT_INT);
            if (!in_array($current_fid, $active_forums)) {
                return;
            }
        }

        if ($mybb->user['uid'] == 0) return;

        $fid = (int)$mybb->settings['charakterfarben_fid'];
        if (!$fid || !isset($mybb->user['fid'.$fid])) return;

        $char_name = strtolower(trim($mybb->user['fid'.$fid]));
        $clean_name = preg_replace('/[^a-z0-9]/', '', $char_name);
        if (empty($clean_name)) return;

        // Farbe des Benutzers holen
        $current_theme_id = (int)$mybb->user['style'];
        $query = $db->simple_select("charakterfarben", "color", "uid = '{$mybb->user['uid']}' AND tid = '{$current_theme_id}'", ['limit' => 1]);
        $color_data = $db->fetch_array($query);

        if ($color_data && !empty($color_data['color'])) {
            
            $color_hex = $color_data['color'];

            // SCHRITT 1: CSS immer laden, damit manuelle Tags funktionieren
            $headerinclude .= "\n<style type=\"text/css\">.post_body {$clean_name}, .post_body span.{$clean_name} { color: {$color_hex} !important; }</style>\n";
            
            // ### FINALE KORREKTUR ###
            // SCHRITT 2: Automatik nur ausführen, wenn die Nachricht existiert und ein String ist
            if (isset($mybb->input['message']) && is_string($mybb->input['message']))
            {
                $message = &$mybb->input['message'];

                // Und nur, wenn keine manuellen Tags vorhanden sind
                if (strpos($message, "<{$clean_name}>") === false) 
                {
                    $regex = '/"([^"]*)"|„([^“]*)“|“([^”]*)”|«([^»]*)»|»([^«]*)«/u';
                    
                    $message = preg_replace_callback(
                        $regex,
                        function ($matches) use ($color_hex) {
                            return '[color='.$color_hex.']'.$matches[0].'[/color]';
                        },
                        $message
                    );
                }
            }
            // ### ENDE KORREKTUR ###
        }
    }
}


function charakterfarben_usercp_page()
{
    global $db, $lang, $mybb, $templates, $header, $headerinclude, $footer, $usercpnav;
    
    if($mybb->input['action'] == "charakterfarben") {
        add_breadcrumb($lang->charakterfarben_ucp_nav, "usercp.php?action=charakterfarben");
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

function charakterfarben_get_theme_background($tid) {
    global $db; $tid = (int)$tid; $query = $db->simple_select("themestylesheets", "stylesheet", "tid='{$tid}' AND name IN ('global.css', 'normalize.css')", ['order_by' => 'sid', 'order_dir' => 'DESC', 'limit' => 1]);
    $stylesheet = $db->fetch_field($query, "stylesheet"); if ($stylesheet && preg_match('/body\s*{[^}]*background(-color)?:\s*([^;}\s]+)/i', $stylesheet, $matches)) { return $matches[2]; } return '#282c34';
}

function charakterfarben_nav() {
	global $mybb, $templates, $lang, $usercpmenu;
	$lang->load("charakterfarben");
	eval("\$usercpmenu .= \"".$templates->get("charakterfarben_nav_usercp")."\";");
}

// #################### ACP-SEITE FUNKTIONEN (KOMPLETTPAKET) ####################

// Hook: Fügt den Menüpunkt im Admin-CP unter "Konfiguration" hinzu
$plugins->add_hook("admin_config_menu", "charakterfarben_admin_menu");
function charakterfarben_admin_menu(&$sub_menu)
{
    $sub_menu[] = array(
        'id' => 'charakterfarben',
        'title' => 'Charakterfarben verwalten',
        'link' => 'index.php?module=config-charakterfarben'
    );
}

// Hook: Definiert, welche Aktionen unser Modul behandeln kann
$plugins->add_hook("admin_config_action_handler", "charakterfarben_admin_action_handler");
function charakterfarben_admin_action_handler(&$actions)
{
    $actions['charakterfarben'] = array(
        'active' => 'charakterfarben',
        'file' => 'charakterfarben_admin_page' // Interner Verweis
    );
}

// Hook: Fügt Berechtigungen für unser Modul hinzu
$plugins->add_hook("admin_config_permissions", "charakterfarben_admin_permissions");
function charakterfarben_admin_permissions(&$admin_permissions)
{
    $admin_permissions['charakterfarben'] = 'Darf Charakterfarben für Benutzer verwalten?';
}

// Hook: Wird bei jedem Laden einer Admin-Seite aufgerufen und steuert die Anzeige.
$plugins->add_hook("admin_load", "charakterfarben_admin_load");
function charakterfarben_admin_load()
{
    global $page, $mybb;

    // Nur ausführen, wenn wir uns wirklich auf unserer Seite befinden
    if ($page->active_action == 'charakterfarben') {
        // Die Funktion aufrufen, die die Seite baut
        charakterfarben_admin_page();
    }
}


// Diese Funktion baut die eigentliche Verwaltungsseite auf
function charakterfarben_admin_page()
{
    global $mybb, $db, $page;

    $page->add_breadcrumb_item('Charakterfarben verwalten', "index.php?module=config-charakterfarben");
    
    // ########## VERARBEITUNG DES SPEICHER-FORMULARS ##########
    if ($mybb->request_method == 'post' && isset($mybb->input['save_colors'])) {
        verify_post_check($mybb->input['my_post_key']);

        $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
        $user = get_user($uid);

        if (!$user) {
            flash_message('Der angegebene Benutzer konnte nicht gefunden werden.', 'error');
            admin_redirect('index.php?module=config-charakterfarben');
        }

        $colors = $mybb->input['colors'];
        if (is_array($colors)) {
            foreach ($colors as $tid => $color) {
                $tid = (int)$tid;
                if (empty($color)) {
                    $db->delete_query("charakterfarben", "uid = {$uid} AND tid = {$tid}");
                }
                elseif (preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color)) {
                    $db->replace_query("charakterfarben", [
                        'uid' => $uid, 
                        'tid' => $tid, 
                        'color' => $db->escape_string($color)
                    ]);
                }
            }
        }
        flash_message("Die Farben für {$user['username']} wurden erfolgreich gespeichert.", 'success');
        admin_redirect('index.php?module=config-charakterfarben&action=edit&uid=' . $uid);
    }
    
    // ########## SEITENAUFBAU ##########
    $page->output_header('Charakterfarben verwalten');

    // Oben: Formular zur Benutzerauswahl
    $user_select_form = new Form("index.php", "get");
    echo $user_select_form->generate_hidden_field('module', 'config-charakterfarben');
    echo $user_select_form->generate_hidden_field('action', 'edit');

    $user_select_container = new FormContainer("Benutzer zur Bearbeitung auswählen");
    
    $user_options = array();
    $user_query = $db->simple_select("users", "uid, username", "uid > 0", array('order_by' => 'username', 'order_dir' => 'ASC'));
    while($user = $db->fetch_array($user_query))
    {
        $user_options[$user['uid']] = htmlspecialchars_uni($user['username']);
    }
    
    $user_select_container->output_row("Benutzer auswählen", "Wähle einen Benutzer aus der Liste, um dessen Farben zu verwalten.", $user_select_form->generate_select_box('uid', $user_options, $mybb->get_input('uid', MyBB::INPUT_INT)), 'uid');
    
    $user_select_container->end();
    
    $user_select_buttons[] = $user_select_form->generate_submit_button("Benutzer bearbeiten");
    $user_select_form->output_submit_wrapper($user_select_buttons);
    $user_select_form->end();

    // Unten: Formular zum Bearbeiten der Farben (nur wenn ein Benutzer ausgewählt ist)
    if ($mybb->input['action'] == 'edit' && $mybb->get_input('uid', MyBB::INPUT_INT) > 0)
    {
        $uid_to_edit = $mybb->get_input('uid', MyBB::INPUT_INT);
        $user = get_user($uid_to_edit);

        $form = new Form("index.php?module=config-charakterfarben", "post");
        echo $form->generate_hidden_field('uid', $user['uid']);
        echo $form->generate_hidden_field('save_colors', '1');

        $form_container = new FormContainer("Farben für {$user['username']} festlegen");
        
        $themes_query = $db->simple_select("themes", "tid, name", "tid > 0", ['order_by' => 'name']);
        while ($theme = $db->fetch_array($themes_query)) {
            $tid = (int)$theme['tid'];
            
            $color_query = $db->simple_select("charakterfarben", "color", "uid={$uid_to_edit} AND tid={$tid}");
            $saved_color = $db->fetch_field($color_query, "color") ?: '';

            $preview_bg_color = charakterfarben_get_theme_background($tid);

            $field_html = '
                <div style="display: flex; align-items: center; gap: 15px;">
                    <input type="color" name="colors['.$tid.']" value="'.$saved_color.'" id="color_picker_'.$tid.'" data-preview-id="preview_'.$tid.'">
                    <div id="preview_'.$tid.'" style="background-color: '.$preview_bg_color.'; padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 100%;">
                        <strong>Charakter:</strong> "So sieht die wörtliche Rede aus."
                    </div>
                </div>';

            $form_container->output_row("Farbe für Theme '{$theme['name']}'", "Wähle eine Farbe oder lasse das Feld leer, um die Zuweisung zu entfernen.", $field_html);
        }

        $form_container->end();
        $buttons[] = $form->generate_submit_button("Farben speichern");
        $form->output_submit_wrapper($buttons);
        $form->end();
    }

    // ##### FINALES JAVASCRIPT #####
    // Wir geben das Skript direkt aus, da $page->footer nicht zuverlässig war.
    echo '
    <script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("input[type=\'color\']").forEach(function(picker) {
            
            const updateColor = function(colorValue) {
                const previewId = picker.dataset.previewId;
                const previewDiv = document.getElementById(previewId);
                if (previewDiv) {
                    previewDiv.style.setProperty("color", colorValue, "important");
                }
            };

            // Setze die Anfangsfarbe für jedes Feld beim Laden der Seite
            if (picker.value) {
                updateColor(picker.value);
            }

            // Aktualisiere die Farbe bei jeder Änderung
            picker.addEventListener("input", function(event) {
                updateColor(event.target.value);
            });
        });
    });
    </script>';

    $page->output_footer();
    die();
}

?>