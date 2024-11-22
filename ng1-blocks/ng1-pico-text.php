<?php
/**
* name : ng1-picto-text
* title : Ng1 Picto Texte 2024
* align : wide
* description : Picto et texte
* category : ng1-blocks
* keywords: ['ng1', 'block','picto','text']
* withoutContainer : true
* withoutWrapper : true
*/



	if ($enligne) {
		$inlineclass= 'enligne';
	}else{
		$inlineclass= 'encolone';
	}
	ob_start(); ?>
	   <span class="<?php echo $picto .' picto-size-'.$fontsize;?>" style="<?php if (!empty($color)) {echo "color:".$color; } ?>"></span>
    <?php	$span_picto = ob_get_clean();
	ob_start();
	?>

<?php if (! $useurl & empty($txt)): ?>
	<?php echo $span_picto; ?>
<?php elseif ($useurl && !empty($url)) : ?>
	<a href="<?php echo $url; ?>" class='picto-link <?php echo $inlineclass ?>'>
		<?php echo $span_picto; ?>
		 <?php if ($txt): ?>
		 	<div class="picto-txt">
       		<?php echo $txt ?>
       		</div>
   		 <?php endif ?>
	</a>
<?php else: ?>
	<div class="pito-text <?php echo $inlineclass ?>" >
	<?php echo $span_picto; ?>
		 <?php if ($txt): ?>
       		<div class="picto-txt">
       		<?php echo $txt ?>
       		</div>
   		 <?php endif ?>
   </div>
<?php endif ?>


	<?php
	$return = ob_get_contents();
	ob_end_clean();
	echo $return;