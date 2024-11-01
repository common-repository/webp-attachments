<?php 
/*
Plugin Name: Webp Attachments
Description: Generate and display optimized webp attachments
Version: 1.4
Requires PHP: 5.6
Author: Nicolas Guitton

/**
 * Plugin
 */
class ngwebpattachments
{
    //TODO : display source only for img where source format < src format 

    public $uploadDir = false;
    public $gdLoaded = false;

    public function __construct()
    {
        $wpUploadDir = wp_upload_dir();
        $this->uploadDir = $wpUploadDir['basedir'].'/';

        add_action('admin_init', array($this,'webpAdminInit'), 12);
        add_action('admin_menu', array($this,'webpAdminMenu'), 12);
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'webpAdminSettingsLink'));

        //Is Gd is loaded
        if(extension_loaded('gd')) {
            $this->gdLoaded = true;
            add_filter('wp_generate_attachment_metadata', array($this, 'filter_wp_generate_attachment_metadata'), 10, 2);
            add_action('delete_attachment', array($this, 'action_delete_attachment'), 10, 1); 
            add_filter('the_content', array($this, 'filter_the_content'), 10, 1);
            add_filter('post_thumbnail_html', array($this, 'filter_post_thumbnail_html'), 10, 5);
        }
    }

    public function webpAdminSettingsLink($links) { 
        $settings_link = '<a href="options-general.php?page=webp-config">'.__( 'Settings' ).'</a>'; 
        array_unshift($links, $settings_link); 
        return $links; 
    }

    public function webpAdminMenu() {
        add_submenu_page(
            'options-general.php', 
            'Webp Attachments', 
            'Webp Attachments', 
            'manage_options', 
            'webp-config',
            array($this, 'iaWebpAdmin')
        );
    }

    public function iaWebpAdmin() {
        include_once(__DIR__.'/views/config-admin.tpl.php');
    }

    public function webpAdminInit() {
        if(isset($_POST['action']) && ($_POST['action'] == 'ngwebp_generate_image_missing' || $_POST['action'] == 'ngwebp_regenerate_image')) {
            $args = array('post_type'=>'attachment','numberposts'=>-1,'post_status'=>null);
            $attachments = get_posts($args);
            if($attachments) {
                foreach($attachments as $attachment) {
                    if(wp_attachment_is_image($attachment->ID)) {
                        $attachmentMetadata = wp_get_attachment_metadata($attachment->ID);
                        if($_POST['action'] == 'ngwebp_regenerate_image' || !isset($attachmentMetadata['ngwebpattachments']) || $attachmentMetadata['ngwebpattachments'] != 'generated') {
                            if(is_file($this->uploadDir.$attachmentMetadata['file'])) {
                                $this->generateWebp($this->uploadDir.$attachmentMetadata['file']);
                            }
                            if(isset($attachmentMetadata['original_image']) && is_file(dirname($this->uploadDir.$attachmentMetadata['file']).'/'.$attachmentMetadata['original_image'])) {
                                $this->generateWebp(dirname($this->uploadDir.$attachmentMetadata['file']).'/'.$attachmentMetadata['original_image']);
                            }
                            foreach ($attachmentMetadata['sizes'] as $size) {
                                if(is_file(dirname($this->uploadDir.$attachmentMetadata['file']).'/'.$size['file'])) {
                                    $this->generateWebp(dirname($this->uploadDir.$attachmentMetadata['file']).'/'.$size['file']);
                                }
                            }

                            $attachmentMetadata['ngwebpattachments'] = 'generated';
                            wp_update_attachment_metadata($attachment->ID, $attachmentMetadata);
                        }
                    }
                }
            }
        }
    }

    //Filter to generate webp when attachment is uploaded
    public function filter_wp_generate_attachment_metadata($metadata, $attachmentId) { 
        if (!wp_attachment_is_image($attachmentId)) {
            return $metadata;
        }

        if(is_file($this->uploadDir.$metadata['file'])) {
            $this->generateWebp($this->uploadDir.$metadata['file']);
        }
        if(isset($metadata['original_image']) && is_file(dirname($this->uploadDir.$metadata['file']).'/'.$metadata['original_image'])) {
            $this->generateWebp(dirname($this->uploadDir.$metadata['file']).'/'.$metadata['original_image']);
        }
        foreach ($metadata['sizes'] as $size) {
            if(is_file(dirname($this->uploadDir.$metadata['file']).'/'.$size['file'])) {
                $this->generateWebp(dirname($this->uploadDir.$metadata['file']).'/'.$size['file']);
            }
        }
        $metadata['ngwebpattachments'] = 'generated';

        return $metadata;
    }

    //Delete webp when delete attachment
    public function action_delete_attachment($attachmentId) { 
        $attachmentMetadata = wp_get_attachment_metadata($attachmentId);
        $webpPathFile = $this->getWebpPathFile($this->uploadDir.$attachmentMetadata['file']);
        if(is_file($webpPathFile)) {
        }
        
        if(isset($attachmentMetadata['original_image'])) {
            $webpPathFile = $this->getWebpPathFile(dirname($this->uploadDir.$attachmentMetadata['file']).'/'.$attachmentMetadata['original_image']);
            if(is_file($webpPathFile)) {
            }
        }

        foreach ($attachmentMetadata['sizes'] as $size) {
            $webpPathFile = $this->getWebpPathFile(dirname($this->uploadDir.$attachmentMetadata['file']).'/'.$size['file']);
            if(is_file($webpPathFile)) {
            }
        }
    }

    public function generateWebp($pathFile, $quality = 80) {
        $mimeType = mime_content_type($pathFile);
        $pathParts = pathinfo($pathFile);
        $img = false;
        if($mimeType == 'image/gif')
            $img = imagecreatefromgif($pathFile);
        elseif($mimeType == 'image/jpeg')
            $img = imagecreatefromjpeg($pathFile);
        elseif($mimeType == 'image/png')
            $img = imagecreatefrompng($pathFile);

        if($img !== false) {
            $webpPathFile = $this->getWebpPathFile($pathFile);
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            $ret = imagewebp($img, $webpPathFile, $quality);
            imagedestroy($img);
            return $webpPathFile;
        }
        return false;
    }

    public function getWebpPathFile($pathFile) {
        $pathParts = pathinfo($pathFile);
        return $pathParts['dirname'].'/'.$pathParts['filename'].'.webp';
    }

    public function filter_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) { 
        // Add and remove <figure> and </figure>, just to use filter_the_content()
        return str_replace(array('<figure>', '</figure>'), '', $this->filter_the_content('<figure>'.$html.'</figure>'));
    }

    public function filter_the_content($content) {
        if(!is_admin()) {
            if(strpos($content, '<figure') !== false) {

                //extract figures tag
                if(preg_match_all('#<figure.*</figure>#Usmi', $content, $figureTags, PREG_PATTERN_ORDER)) {
                    foreach ($figureTags[0] as $figureTagKey => $figureTag) {

                        //extract img tag
                        if (preg_match_all('#<img[^>]*src[^>]*>#Usmi', $figureTag, $imgTags, PREG_PATTERN_ORDER)) {
                            foreach ($imgTags[0] as $imgTagKey => $imgTag) {
                                // Add <picture> ..... </picture> in content if not present
                                if(stripos($figureTag, '<picture>') === false)
                                    $content = str_replace($imgTag, '<picture>'.$imgTag.'</picture>', $content);

                                //extract img tag srcset attr
                                if (preg_match_all( '#srcset=(?:"|\')(?!data)(.*)(?:"|\')#Usmi', $imgTag, $srcsetAttrs, PREG_PATTERN_ORDER)) {
                                    $srcsetAttr = (isset($srcsetAttrs[1][0]))?$srcsetAttrs[1][0]:array();
                                    $allSrcsetSrcs = explode(',', $srcsetAttr);
                                    
                                    foreach ($allSrcsetSrcs as $allSrcsetSrcKey => $allSrcsetSrc) {
                                        $tmpAllSrcsetSrc = explode(' ', trim($allSrcsetSrc));

                                        $srcAttr = trim($tmpAllSrcsetSrc[0]);
                                        if($allSrcsetSrcKey == 0)
                                            $srcMedia = 'media="(min-width: '.str_replace('w', '', $tmpAllSrcsetSrc[1]).'px)"';
                                        else
                                            $srcMedia = 'media="(max-width: '.str_replace('w', '', $tmpAllSrcsetSrc[1]).'px)"';

                                        preg_match_all('/-(\d+x\d+)(?=\.jpg|jpeg|png|gif$)/i', $srcAttr, $attachmentUrlMatches);
                                        $attachmentFormat = (isset($attachmentUrlMatches[1][0]))?$attachmentUrlMatches[1][0]:false;
                                        $attachmentOriginalUrl = $srcAttr;
                                        $attachmentUrl = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachmentOriginalUrl);
                                        $attachmentId = $this->ia_attachment_url_to_postid($attachmentUrl);
                                        $attachmentMetadata = wp_get_attachment_metadata($attachmentId);
                                        $attachmentPathFile = $this->uploadDir.$attachmentMetadata['file'];
                                        $mimeType = mime_content_type($attachmentPathFile);
                                        $pathParts = pathinfo($attachmentPathFile);

                                        //TODO : display source only for img where source format < src format
                                        if($attachmentFormat === false) {
                                            $webpPathFile = $attachmentPathFile;
                                        }
                                        else {
                                            $webpPathFile = $this->removeScaledFromFilename($attachmentPathFile);
                                            $webpPathFile = $this->replaceExtensionFile($webpPathFile, $pathParts['extension'], 'webp');
                                        }
                                        if(is_file($webpPathFile)) {
                                            $webpUrlFile = $this->replaceExtensionFile($attachmentOriginalUrl, $pathParts['extension'], 'webp');

                                            //add source tag to content
                                            $sourceFiles = '<source srcset="'.$webpUrlFile.'" '.$srcMedia.' type="image/webp">';
                                            $sourceFiles .= '<source srcset="'.$attachmentOriginalUrl.'" '.$srcMedia.' type="'.$mimeType.'">';
                                            $content = str_replace($imgTag, $sourceFiles.$imgTag, $content);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $content;
    }

    //Function to replace attachment_url_to_postid (return 0 for "-scaled" image)
    public function ia_attachment_url_to_postid($imageUrl) {
        global $wpdb;
        $imageUrl = $this->removeScaledFromFilename($imageUrl);
        $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $imageUrl )); 
        if(isset($attachment[0]))
            return $attachment[0];

        return false;
    }

    //Function to replace extension in filename
    public function replaceExtensionFile($filename, $oldExtension, $newExtension) {
        return preg_replace('/.([^.]*)$/', '.'.$newExtension, $filename);
    }

    //Function to remove -scaled from filename
    public function removeScaledFromFilename($filename) {
        return preg_replace('/(-scaled)(.[^.]*)$/', '${2}', $filename);
    }
}

$ngwebpattachments = new ngwebpattachments();