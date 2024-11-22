<?php
/**
* name : ng1-slider
* title : Ng1 Slider 2024
* align : wide
* description : Slider
* category : ng1-blocks
* keywords: ['ng1', 'block','slider']
* withoutContainer : false
* withoutWrapper : true
*/
?>
<?php extract($block); ?>
<div id="<?php echo $sliderId ?>" class="slider-ng1 align<?php echo $align ?>">
	<?php foreach ($imagesId as $img) :	?>
		<div class="item <?php echo $sliderId ?>">
			<?php if ($useFancy == true): ?>
				<?php the_fancy( $img, $imgSize,$imgSmallSize ); ?>
			<?php else: ?>
				<?php echo  wp_get_attachment_image( $img, $imgSize, $imgSmallSize); ?>
			<?php endif ?>
		</div>
	<?php endforeach; ?>
</div>
