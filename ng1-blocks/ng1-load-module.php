<?php
/**
* name : ng1-load-module
* title : Ng1 module 2022
* align : wide
* description :charge un module NG1
* category : ng1-blocks
* keywords: ['ng1', 'block','module']
* withoutContainer : true
* withoutWrapper : true
*/
?>
<!-- wp:acf/ng1-load-module {"name":"acf/ng1-load-module","data":{"module_id":1741,"_module_id":"field_638f0f39f7acd"},"align":"wide","mode":"preview"} /-->
<!-- /wp:group -->
<?php
$post = get_post($module_id);
if (!empty($post)) $id=$post->ID;
$url_module =  get_page_template_slug($post);
include $url_module;
?>