<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class BannerService {
	public function getBanners(){
		$banners = array();



		if (!Registry::is_exist('addons.banners.status') || Registry::get('addons.banners.status') == 'A') {
            $blocks = \Tygh\BlockManager\Block::instance(0)->getAllUnique();

            $bid = 1;

            foreach($blocks as $block){
                if($block['name'] == 'Android Banners' && $block['type'] == 'banners'){
                    $bid = $block['block_id'];
                }
            }


            $banners_temps = \Tygh\BlockManager\Block::instance(0)->getById($bid);

            $banners_temps = fn_get_banners(array(
                'item_ids' => $banners_temps['content']['items']['item_ids']
            ));

            if (!empty($banners_temps)) {
				foreach ($banners_temps[0] as $banners_temp){
					$main_pair = $banners_temp['main_pair'];
					$icon = $main_pair['icon'];
					$banners[] = array('title' => $banners_temp['banner'], 'url' => $banners_temp['url'], 'img_url' => $icon['http_image_path']);
				}
            }
        }
		
		return $banners;
	}
}

?>