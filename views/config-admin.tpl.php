<div class="wrap">
	<h1 class="wp-heading-inline">Webp Attachments</h1>
	<br>
	<?php
		global $ngwebpattachments;
		if($ngwebpattachments->gdLoaded === true) {
			$countAttachments = 0;
			$countWebpAttachmentsNotGenerated = 0;
			$args = array('post_type'=>'attachment','numberposts'=>-1,'post_status'=>null);
			$attachments = get_posts($args);
			if($attachments) {
				foreach($attachments as $attachment) {
					if(wp_attachment_is_image($attachment->ID)) {
						$countAttachments++;
						$attachmentMetadata = wp_get_attachment_metadata($attachment->ID);
						if(!isset($attachmentMetadata['ngwebpattachments']) || $attachmentMetadata['ngwebpattachments'] != 'generated') {
							$countWebpAttachmentsNotGenerated++;
						}
					}
				}
			}
	?>
			<?php if($countWebpAttachmentsNotGenerated > 0) { ?>
				<form action="" method="POST">
					<p><?php echo $countWebpAttachmentsNotGenerated ?>/<?php echo $countAttachments; ?> webp attachments are not generated !</p>
					<input type="hidden" name="action" value="ngwebp_generate_image_missing"><br>
					<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Generate missing webp images"></p>
				</form>
			<?php } else { ?>
				<form action="" method="POST">
					<p>All webp attachments are generated! Great Job!</p>
					<input type="hidden" name="action" value="ngwebp_regenerate_image"><br>
					<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Re-Generate all webp images"></p>
				</form>
			<?php } ?>
	<?php
		} else {
	?>
			<p>Php extension GD is not installed.</p>
			<p><a href="https://www.php.net/manual/en/image.installation.php" target="_blank">How install/configure GD?</a></p>
	<?php
		}
	?>
</div>