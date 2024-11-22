<?php
/**
 * Plugin Name: ACF Block Auto-Loader 2
 * Plugin URI: http://www.ng1.fr
 * Description: Automatically registers ACF Blocks from your theme directory. Simply add your block files in the 'acf-blocks' 
 * theme folder with proper PHP documentation to register them. Supports automatic CSS and JS file loading.
 * Version: 1.0.0
 * Author: Nicolas GEHIN
 * Author URI: http://www.ng1.fr
 * License: GPL2
 * Text Domain: acf-block-auto-loader
 * Requires Plugins: advanced-custom-fields-pro
 */

if (!defined('ABSPATH')) exit;

class AcfBlockAutoLoader {
    private static $instance = null;
    private $debug = true;
    
    // Configuration des dossiers de blocs
    private $blocks_directories = array(
        'theme' => array(
            'acf-blocks',
            'ng1-blocks'
        ),
        'plugin' => array(
            'ng1-blocks'
        )
    );

    // Ajout de la propriété pour le dossier JSON
    private $json_directory = 'block-json';

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->registerBlocks();
        }
        return self::$instance;
    }

    private function getPluginPath() {
        return plugin_dir_path(__FILE__);
    }

    private function getPluginUrl() {
        return plugin_dir_url(__FILE__);
    }

    private function checkBlocksFolder() {
        $block_list = array();

        // Parcourir les dossiers du thème
        foreach ($this->blocks_directories['theme'] as $directory) {
            $theme_dir = get_stylesheet_directory() . '/' . $directory;
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Checking theme directory: ' . $theme_dir);
            }
            $block_list = array_merge($block_list, $this->scanBlocksDirectory($theme_dir, 'theme', $directory));
        }

        // Parcourir les dossiers du plugin
        foreach ($this->blocks_directories['plugin'] as $directory) {
            $plugin_dir = $this->getPluginPath() . $directory;
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Checking plugin directory: ' . $plugin_dir);
            }
            $block_list = array_merge($block_list, $this->scanBlocksDirectory($plugin_dir, 'plugin', $directory));
        }

        return $block_list;
    }

    private function scanBlocksDirectory($dir, $source, $directory) {
        if (!is_dir($dir)) {
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Directory does not exist: ' . $dir);
            }
            return array();
        }

        $block_list = array();
        $items = scandir($dir);

        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Found items in ' . $dir . ': ' . print_r($items, true));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $item_path = $dir . '/' . $item;
            
            // Si c'est un dossier
            if (is_dir($item_path)) {
                if ($this->debug) {
                    error_log('ACF Block Auto-Loader: Checking directory: ' . $item);
                }
                
                $index_file = $item_path . '/index.php';
                if (file_exists($index_file)) {
                    if ($this->debug) {
                        error_log('ACF Block Auto-Loader: Found block file: ' . $index_file);
                    }
                    
                    $file_data = array(
                        'filename' => $item,
                        'extension' => 'php',
                        'file' => $item . '/index.php',
                        'source' => $source,
                        'directory' => $directory,
                        'attr' => $this->getBlockComment($index_file)
                    );
                    
                    $block_list[] = $file_data;
                }
            }
            // Si c'est un fichier PHP
            elseif (is_file($item_path) && pathinfo($item_path, PATHINFO_EXTENSION) === 'php') {
                if ($this->debug) {
                    error_log('ACF Block Auto-Loader: Found PHP file: ' . $item);
                }
                
                $file_data = array(
                    'filename' => pathinfo($item, PATHINFO_FILENAME),
                    'extension' => 'php',
                    'file' => $item,
                    'source' => $source,
                    'directory' => $directory,
                    'attr' => $this->getBlockComment($item_path)
                );
                
                $block_list[] = $file_data;
            }
        }

        return $block_list;
    }

    public function registerBlocks() {
        $available_blocks = $this->checkBlocksFolder();
        
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Found ' . count($available_blocks) . ' blocks');
            error_log('ACF Block Auto-Loader: Blocks: ' . print_r($available_blocks, true));
        }
        
        foreach ($available_blocks as $block) {
            extract($block);
            
            if (!isset($attr['name'])) {
                $attr['name'] = 'acf-block/' . $filename;
            }
            
            if (!isset($attr['title'])) {
                $attr['title'] = ucfirst($filename);
            }
            
            // Gestion des assets en fonction de la source (theme ou plugin)
            if (array_key_exists('withcss', $attr) && $attr['withcss'] == true) {
                $attr['enqueue_style'] = $this->getAssetUrl($source, $directory, $filename, 'css');
            }
            
            if (array_key_exists('withjs', $attr) && $attr['withjs'] == true) {
                $attr['enqueue_script'] = $this->getAssetUrl($source, $directory, $filename, 'js');
            }
            
            $attr['render_callback'] = array($this, 'renderCallback');
            $attr['file'] = $file;
            $attr['source'] = $source;
            $attr['directory'] = $directory;
            
            if (isset($attr['name']) && isset($attr['title'])) {
                if ($this->debug) {
                    error_log('ACF Block Auto-Loader: Registering block: ' . $attr['name']);
                }
                acf_register_block_type($attr);
            }
        }
    }

    private function getAssetUrl($source, $directory, $filename, $type) {
        if ($source === 'theme') {
            return get_stylesheet_directory_uri() . '/' . $directory . '/assets/' . $type . '/' . $filename . '.' . $type;
        } else {
            return $this->getPluginUrl() . $directory . '/assets/' . $type . '/' . $filename . '.' . $type;
        }
    }

    public function renderCallback($block) {
        // Récupérer le chemin correct en fonction de la source
        $base_path = ($block['source'] === 'theme') ? get_stylesheet_directory() : $this->getPluginPath();
        $file = $base_path . $block['directory'] . '/' . $block['file'];
        
        $blockid = $block['id'];
        $base_class = str_replace("acf/", '', $block['name']);
        $align_class = $block['align'] ? 'align' . $block['align'] : '';
        $fields = get_fields();
        if ($fields) {
            extract($fields);
        }
        
        if (file_exists($file)) {
            include($file);
        }
    }

    /**
     * Récupère les commentaires de documentation du bloc
     */
    private function getBlockComment($file) {
        if (!file_exists($file)) {
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: File does not exist: ' . $file);
            }
            return array();
        }

        $source = file_get_contents($file);
        $tokens = token_get_all($source);
        $comment = array(T_DOC_COMMENT);
        $comments = array();
        
        foreach ($tokens as $token) {
            if (!in_array($token[0], $comment)) {
                continue;
            }
            $comments[] = $token[1];
        }
        
        if (empty($comments)) {
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: No doc comments found in ' . $file);
            }
            return array();
        }

        return $this->lineCommentToArray($comments[0]);
    }

    /**
     * Convertit les commentaires en tableau d'attributs
     */
    private function lineCommentToArray($comment) {
        if (empty($comment)) {
            return array();
        }
        
        $lines = explode("\n", $comment);
        $return = array();
        
        foreach ($lines as $line) {
            if (!preg_match('/\/\*\*/', $line) && !preg_match('/\*\//', $line)) {
                $line = trim(str_replace('* ', "", $line));
                if (empty($line)) continue;
                
                $parts = explode(":", $line, 2);
                if (count($parts) === 2) {
                    $key = str_replace(' ', '_', strtolower(trim($parts[0])));
                    $value = trim($parts[1]);
                    
                    // Conversion des valeurs booléennes
                    if (strtolower($value) === 'true') {
                        $value = true;
                    } elseif (strtolower($value) === 'false') {
                        $value = false;
                    }
                    
                    // Conversion des listes de mots-clés en tableau
                    if ($key === 'keywords') {
                        $value = array_map('trim', explode(',', $value));
                    }
                    
                    $return[$key] = $value;
                }
            }
        }
        
        if ($this->debug && empty($return)) {
            error_log('ACF Block Auto-Loader: No attributes found in comment');
        }
        
        return $return;
    }

    public function __construct() {
        // Déplacer les filtres avant l'initialisation d'ACF
        add_action('init', array($this, 'initializeJsonHandling'), 1);
        
        // Ajouter une action pour la sauvegarde des groupes de champs
        add_action('acf/update_field_group', array($this, 'onFieldGroupUpdate'));
        
        // Ajouter l'action pour importer les champs automatiquement
        add_action('admin_init', array($this, 'importJsonFields'));
    }

    public function initializeJsonHandling() {
        // Ajouter les filtres pour la gestion des JSON ACF
        add_filter('acf/settings/save_json', array($this, 'setJsonSavePath'));
        add_filter('acf/settings/load_json', array($this, 'addJsonLoadPath'));
    }

    /**
     * Définit le chemin de sauvegarde des fichiers JSON
     */
    public function setJsonSavePath($path) {
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Checking JSON save path');
            error_log('ACF Block Auto-Loader: Default path: ' . $path);
        }

        // Vérifie si le groupe de champs est lié à un bloc
        if ($this->isFieldGroupForBlock()) {
            $new_path = $this->getPluginPath() . $this->json_directory;
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Group is for block, using path: ' . $new_path);
            }
            
            // Créer le dossier s'il n'existe pas
            if (!is_dir($new_path)) {
                mkdir($new_path, 0755, true);
            }
            
            return $new_path;
        }

        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Group is not for block, using default path');
        }
        return $path;
    }

    /**
     * Vérifie si le groupe de champs est associé à un bloc
     */
    private function isFieldGroupForBlock() {
        // Récupère le groupe de champs en cours d'édition
        $screen = get_current_screen();
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Current screen: ' . ($screen ? $screen->base : 'null'));
        }

        if (!$screen || $screen->base !== 'acf-field-group') {
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Not on field group edit screen');
            }
            return false;
        }

        // Récupère l'ID du groupe de champs
        $field_group_id = isset($_GET['post']) ? $_GET['post'] : false;
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Field group ID: ' . ($field_group_id ? $field_group_id : 'not found'));
        }

        if (!$field_group_id) {
            return false;
        }

        // Récupère les règles de localisation du groupe
        $field_group = acf_get_field_group($field_group_id);
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Field group data: ' . print_r($field_group, true));
        }

        if (!$field_group || !isset($field_group['location'])) {
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: No location rules found');
            }
            return false;
        }

        // Vérifie si le groupe est associé à un bloc
        foreach ($field_group['location'] as $location_group) {
            foreach ($location_group as $location_rule) {
                if ($this->debug) {
                    error_log('ACF Block Auto-Loader: Checking rule: ' . print_r($location_rule, true));
                }

                if ($location_rule['param'] === 'block') {
                    $is_plugin_block = $this->isBlockFromPlugin($location_rule['value']);
                    if ($this->debug) {
                        error_log('ACF Block Auto-Loader: Found block rule. Is plugin block? ' . ($is_plugin_block ? 'yes' : 'no'));
                    }

                    if ($is_plugin_block) {
                        return true;
                    }
                }
            }
        }

        if ($this->debug) {
            error_log('ACF Block Auto-Loader: No matching block rules found');
        }
        return false;
    }

    /**
     * Vérifie si le bloc appartient au plugin
     */
    private function isBlockFromPlugin($block_name) {
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Checking if block belongs to plugin: ' . $block_name);
        }

        // Enlever le préfixe 'acf/' du nom du bloc si présent
        $block_name = str_replace('acf/', '', $block_name);
        
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Normalized block name: ' . $block_name);
        }

        $available_blocks = $this->checkBlocksFolder();
        foreach ($available_blocks as $block) {
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Comparing with block: ' . ($block['attr']['name'] ?? 'unnamed'));
            }

            if (isset($block['attr']['name']) && 
                $block['attr']['name'] === $block_name && 
                $block['source'] === 'plugin') {
                if ($this->debug) {
                    error_log('ACF Block Auto-Loader: Found matching plugin block');
                }
                return true;
            }
        }

        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Block not found in plugin blocks');
        }
        return false;
    }

    public function addJsonLoadPath($paths) {
        $json_path = $this->getPluginPath() . $this->json_directory;
        
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Adding JSON load path: ' . $json_path);
        }
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($json_path)) {
            mkdir($json_path, 0755, true);
        }
        
        // S'assurer que le chemin n'est pas déjà dans le tableau
        if (!in_array($json_path, $paths)) {
            $paths[] = $json_path;
        }
        
        error_log('ACF Block Auto-Loader: Current JSON paths: ' . print_r($paths, true));
        
        return $paths;
    }

    /**
     * Gère la sauvegarde des groupes de champs
     */
    public function onFieldGroupUpdate($field_group) {
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Field group update detected');
            error_log('ACF Block Auto-Loader: Field group data: ' . print_r($field_group, true));
        }

        // Vérifie si le groupe est lié à un bloc
        if ($this->isFieldGroupForBlockFromLocation($field_group['location'])) {
            $json_path = $this->getPluginPath() . 'block-json';
            
            // Créer le dossier s'il n'existe pas
            if (!is_dir($json_path)) {
                if ($this->debug) {
                    error_log('ACF Block Auto-Loader: Creating JSON directory: ' . $json_path);
                }
                if (!mkdir($json_path, 0755, true)) {
                    error_log('ACF Block Auto-Loader: Failed to create JSON directory');
                    return;
                }
            }

            // Récupérer tous les champs du groupe
            $fields = acf_get_fields($field_group);
            $field_group['fields'] = $fields;
            
            // Construire le chemin complet du fichier
            $file_path = $json_path . '/' . $field_group['key'] . '.json';
            
            // Encoder les données en JSON
            $json = acf_json_encode($field_group);
            
            // Écrire le fichier
            $result = file_put_contents($file_path, $json);
            
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Saved field group to: ' . $file_path);
                error_log('ACF Block Auto-Loader: Fields saved: ' . print_r($fields, true));
            }
        }
    }

    /**
     * Vérifie si le groupe est lié à un bloc à partir des règles de localisation
     */
    private function isFieldGroupForBlockFromLocation($location) {
        if (!is_array($location)) {
            return false;
        }

        foreach ($location as $location_group) {
            foreach ($location_group as $location_rule) {
                if ($this->debug) {
                    error_log('ACF Block Auto-Loader: Checking location rule: ' . print_r($location_rule, true));
                }

                if ($location_rule['param'] === 'block' && 
                    $this->isBlockFromPlugin($location_rule['value'])) {
                    return true;
                }
            }
        }

        return false;
    }

    // Nouvelle méthode pour importer les champs
    public function importJsonFields() {
        // Vérifier si ACF PRO est actif
        if (!function_exists('acf_get_field_groups')) {
            return;
        }

        $json_path = $this->getPluginPath() . $this->json_directory;
        
        if ($this->debug) {
            error_log('ACF Block Auto-Loader: Checking for JSON files in: ' . $json_path);
        }

        // Scanner le répertoire pour les fichiers JSON
        if (is_dir($json_path)) {
            $json_files = glob($json_path . '/*.json');
            
            if ($this->debug) {
                error_log('ACF Block Auto-Loader: Found JSON files: ' . print_r($json_files, true));
            }

            foreach ($json_files as $json_file) {
                $json_content = json_decode(file_get_contents($json_file), true);
                
                if (!$json_content || !isset($json_content['key'])) {
                    continue;
                }

                // Vérifier si le groupe existe déjà
                $existing_group = acf_get_field_group($json_content['key']);
                
                if (!$existing_group) {
                    if ($this->debug) {
                        error_log('ACF Block Auto-Loader: Importing field group: ' . $json_content['title']);
                    }
                    
                    // Importer le groupe de champs
                    acf_import_field_group($json_content);
                }
            }
        }
    }
}

// Initialisation du plugin
add_action('acf/init', function() {
    AcfBlockAutoLoader::getInstance();
}, 20);