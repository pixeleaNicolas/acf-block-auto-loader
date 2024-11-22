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
}

// Initialisation du plugin
add_action('acf/init', function() {
    AcfBlockAutoLoader::getInstance();
}, 20);