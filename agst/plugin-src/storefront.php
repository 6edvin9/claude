<?php
if(!defined('ABSPATH'))exit;
final class AGST_Storefront {
 static function active(){return function_exists('is_product')&&is_product()&&((class_exists('AGST_QA')&&AGST_QA::preview())||get_post_meta(get_queried_object_id(),'_agst_managed',true)||get_option('agst_full_shop_layout',false));}
 static function boot(){
  add_filter('template_include',function($file){return self::active()?__DIR__.'/single-product.php':$file;},999);
  add_filter('body_class',function($classes){if(self::active())$classes[]='agst-full-layout';return $classes;});
  add_action('wp_enqueue_scripts',function(){if(self::active()){wp_enqueue_style('agst-storefront',plugins_url('storefront.css',__FILE__),[],AGST_Catalog::ASSET_VERSION);wp_enqueue_script('agst-storefront',plugins_url('storefront.js',__FILE__),[],AGST_Catalog::ASSET_VERSION,true);}},99);
  add_action('admin_post_agst_layout',function(){check_admin_referer('agst-layout');try{AGST_Catalog::guard();update_option('agst_full_shop_layout',isset($_POST['enabled']),false);wp_safe_redirect(admin_url('admin.php?page=agst-catalog'));exit;}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}});
 }
 static function row($p){$key=get_post_meta($p->get_id(),'_agst_key',true);if(!$key)return null;foreach(AGST_Catalog::manifest() as $r)if(($key&&$r['key']===$key)||$r['target_id']===$p->get_id())return $r;return null;}
 static function inventory($p){$list=AGST_Catalog::media($p->get_id());$saved=get_post_meta($p->get_id(),'_agst_media_inventory',true);if(is_array($saved))$list=array_merge($list,$saved);$out=[];foreach($list as $v){if(empty($v['url']))continue;$u=AGST_Catalog::local_url($v['url']);if(!$u||$u==='https://www.youtube-nocookie.com/embed/')continue;$v['url']=$u;$identity=$v['type']==='image'?self::image_key($u):$u;if(preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|watch\?v=))([a-zA-Z0-9_-]{11})~',$u,$match))$identity='youtube:'.$match[1];if(!isset($out[$identity]))$out[$identity]=$v;}$bg=self::css_only_images($p->get_id());if($bg){foreach($out as $k=>$v){if($v['type']==='image'&&isset($bg[self::image_key($v['url'])]))unset($out[$k]);}}return array_values($out);}
 // Images referenced only from CSS (old section backgrounds and banners) are decoration, not product imagery.
 static function css_only_images($id){$raw=(string)get_post_field('post_content',$id).' '.(string)get_post_field('post_excerpt',$id).' '.(string)get_post_meta($id,'_elementor_data',true);$raw=str_replace('\\/','/',$raw);$css=[];$tag=[];
  if(preg_match_all('~url\\(\\s*[\\x22\\x27\\\\]*([^\\x22\\x27)\\\\]+)~i',$raw,$m))foreach($m[1] as $u){$l=AGST_Catalog::local_url($u);if($l)$css[self::image_key($l)]=true;}
  if(preg_match_all('~<style\\b[^>]*>(.*?)</style>~is',$raw,$st))foreach($st[1] as $blk){if(preg_match_all('~(?:https?:)?//[^\\s\\x22\\x27)]+|/wp-content/[^\\s\\x22\\x27)]+~i',$blk,$mm))foreach($mm[0] as $u){$l=AGST_Catalog::local_url($u);if($l)$css[self::image_key($l)]=true;}}
  if(preg_match_all('~<img\\b[^>]*\\bsrc=[\\\\]*[\\x22\\x27]([^\\x22\\x27\\\\]+)~i',$raw,$im))foreach($im[1] as $u){$l=AGST_Catalog::local_url($u);if($l)$tag[self::image_key($l)]=true;}
  $p=wc_get_product($id);if($p){foreach(array_merge([$p->get_image_id()],$p->get_gallery_image_ids()) as $aid){$u=$aid?wp_get_attachment_url($aid):'';if($u)$tag[self::image_key(AGST_Catalog::local_url($u))]=true;}}
  return array_diff_key($css,$tag);}
 static function image_key($url){
  $path=(string)parse_url(html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_PATH);
  // WordPress conversion copies share a path stem; keep one display rendition.
  return preg_replace('~\.(?:jpe?g|png|webp|avif)$~i','',$path);
 }
 static function hero_images($p,$images){
  $wanted=[];foreach(array_merge([$p->get_image_id()],$p->get_gallery_image_ids()) as $id){if($id){$u=wp_get_attachment_url($id);if($u)$wanted[self::image_key($u)]=true;}}
  $hero=[];foreach($images as $im)if(isset($wanted[self::image_key($im['url'])])){$hero[]=$im;if(count($hero)===5)break;}
  return $hero?:array_slice($images,0,1);
 }
 static function picture($item,$title,$eager=false){$u=esc_url($item['url']);$alt=esc_attr($item['alt']?:$title);return '<img src="'.$u.'" alt="'.$alt.'" '.($eager?'fetchpriority="high"':'loading="lazy"').' decoding="async">';}
 static function swatch($c){$colors=['black'=>'#202222','bronze'=>'#79634b','white'=>'#f3f0e9','sand'=>'#c6b69e','clay'=>'#9b8b7d','gray'=>'#7c7f80','ipe'=>'#70422c','walnut'=>'#62412e','yellow oak'=>'#bf8d51'];return $colors[strtolower($c)]??'#b4a89a';}
 static function model($p){$r=self::row($p);$media=self::inventory($p);$images=array_values(array_filter($media,function($v){return $v['type']==='image'&&!preg_match('~(?:img\.youtube\.com|i\.ytimg\.com)/~',$v['url']);}));$videos=array_values(array_filter($media,function($v){return in_array($v['type'],['video','embed','link'],true);}));
  $variants=[];$attrs=[];
  if($p->is_type('variable')){foreach($p->get_variation_attributes() as $key=>$values){$opts=[];foreach($values as $value){$term=taxonomy_exists($key)?get_term_by('slug',$value,$key):null;$opts[]=['value'=>$value,'label'=>$term&&!is_wp_error($term)?$term->name:$value];}$attrs[sanitize_title($key)]=['label'=>wc_attribute_label($key),'options'=>$opts];}
   foreach($p->get_children() as $id){$v=wc_get_product($id);if(!$v||$v->get_status()!=='publish')continue;$variants[]=['id'=>$id,'attributes'=>$v->get_attributes(),'price'=>$v->get_price()!==''?wp_strip_all_tags(wc_price(wc_get_price_to_display($v))):'Price to be confirmed','image'=>$v->get_image_id()?AGST_Catalog::local_url(wp_get_attachment_url($v->get_image_id())):'','sku'=>$v->get_sku(),'available'=>$v->is_in_stock()];}
  }
  if(!$r){$facts=[];foreach($p->get_attributes() as $attr){$value=$attr->is_taxonomy()?implode(', ',wc_get_product_terms($p->get_id(),$attr->get_name(),['fields'=>'names'])):implode(', ',$attr->get_options());$facts[]=wc_attribute_label($attr->get_name()).': '.$value;}
   $r=['title'=>$p->get_name(),'family'=>'Aluminum systems','lead'=>wp_trim_words(wp_strip_all_tags($p->get_short_description()),55,'…'),'headline'=>'Built around your project.','features'=>[],'specs'=>$facts,'inclusions'=>[],'post_options'=>[],'story_title'=>'The details make the difference.','story'=>'Review the product configuration, dimensions and supporting media before selecting your material package.','faq'=>[],'short_html'=>''];
  }
  $r['title']=AGST_Fixes::title($p->get_id(),$p->get_name());
  $content=AGST_Content::get($p);if(!empty($content['lead']))$r['lead']=$content['lead'];$agst_sl=trim((string)get_post_meta($p->get_id(),'_agst_seo_lead',true));if($agst_sl!=='')$r['lead']=$agst_sl;$fq=json_decode((string)get_post_meta($p->get_id(),'_agst_seo_faq',true),true);if(is_array($fq)&&$fq)$r['faq']=$fq;$sp=json_decode((string)get_post_meta($p->get_id(),'_agst_seo_specs',true),true);if(is_array($sp)&&$sp)$r['specs']=$sp;
  $hero=self::hero_images($p,$images);$cm=AGST_Convert::media($content['sections']);$in_copy=[];foreach($cm['img'] as $u)$in_copy[self::image_key($u)]=true;foreach($hero as $h)$in_copy[self::image_key($h['url'])]=true;$vk=array_flip($cm['video']);
  $supporting=array_values(array_filter($images,function($im)use($in_copy){return !isset($in_copy[self::image_key($im['url'])]);}));
  $videos=array_values(array_filter($videos,function($v)use($vk){$b=AGST_Convert::video_from_url($v['url']);return !$b||!isset($vk[AGST_Blocks::video_key($b)]);}));
  return ['p'=>$p,'r'=>$r,'hero'=>$hero,'supporting'=>$supporting,'images'=>$images,'videos'=>$videos,'variants'=>$variants,'attributes'=>$attrs,'content'=>$content['sections']];
 }
 static function video($v,$title){$url=$v['url'];$embed='';$thumb='';
  if(preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|watch\?v=))([a-zA-Z0-9_-]{11})~',$url,$m)){$embed='https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1';$thumb='https://img.youtube.com/vi/'.$m[1].'/hqdefault.jpg';}
  elseif(preg_match('~vimeo\.com/(?:video/)?(\d+)~',$url,$m)){$embed='https://player.vimeo.com/video/'.$m[1];parse_str((string)parse_url($url,PHP_URL_QUERY),$q);if(isset($q['h']))$embed.='?h='.rawurlencode($q['h']);}
  if($embed)return '<div class="agx-video"><button class="agx-play" data-embed="'.esc_url($embed).'" aria-label="Play '.esc_attr($title).' video">'.($thumb?'<img src="'.esc_url($thumb).'" alt="" loading="lazy">':'').'<span class="agx-play-symbol">▶</span><span class="agx-play-label">Watch the product video</span></button><a class="agx-video-original" href="'.esc_url($url).'">Open original video ↗</a></div>';
  if($v['type']==='video')return '<div class="agx-video"><video controls playsinline preload="none" aria-label="'.esc_attr($title).' video" src="'.esc_url($url).'"></video></div>';
  return '<a class="agx-button agx-button-line" href="'.esc_url($url).'">View supporting media ↗</a>';
 }
 static function render($model){if(class_exists('AGST_V2')&&AGST_V2::on($model['p']->get_id())){AGST_V2::render($model);return;}extract($model);$title=$r['title'];$shop=wc_get_page_permalink('shop');$contact=get_page_by_path('contact-us')?:get_page_by_path('contact');$contact_url=$contact?get_permalink($contact):'';$price=$p->get_price_html();$help=$contact_url?:'#agx-details';
 ?>
 <main class="agx" id="agx-product" data-product-id="<?php echo (int)$p->get_id();?>">
 <div class="agx-breadcrumb"><a href="<?php echo esc_url(AGST_Catalog::local_url($shop));?>">Shop</a><span>/</span><span><?php echo esc_html($r['family']);?></span><span>/</span><span><?php echo esc_html($title);?></span></div>
 <div class="agx-notices"><?php if(function_exists('wc_print_notices'))wc_print_notices();?></div>
 <section class="agx-hero" id="agx-configure">
  <div class="agx-gallery-hero"><div class="agx-main-image"><?php if($hero):?><button class="agx-zoom" data-full="<?php echo esc_url($hero[0]['url']);?>" aria-label="Enlarge product image"><?php echo self::picture($hero[0],$title,true);?><span class="agx-expand">＋ View full image</span></button><?php else:?><div class="agx-image-pending">Product image coming soon</div><?php endif;?></div>
  <?php if(count($hero)>1):?><div class="agx-thumbnails" aria-label="Product images"><?php foreach($hero as $i=>$im):?><button data-gallery="<?php echo esc_url($im['url']);?>" data-alt="<?php echo esc_attr($im['alt']?:$title);?>" aria-label="Show product image <?php echo $i+1;?>" aria-pressed="<?php echo $i===0?'true':'false';?>"><?php echo self::picture($im,$title);?></button><?php endforeach;?></div><?php endif;?>
  <div class="agx-gallery-footer"><span>Product photos</span><?php if($videos):?><a href="#agx-films">Watch <?php echo count($videos)>1?'the videos':'the video';?> ↗</a><?php endif;?></div></div>
  <?php /* Package section only when the listing has real inclusions or mounting options; otherwise it repeated the spec list. */ $agx_pkg=!empty($r['inclusions'])||!empty($r['post_options']);?><div class="agx-buy-panel"><p class="agx-eyebrow">Aluglobus Aluminum Systems</p><h1><?php echo esc_html($title);?></h1><div class="agx-pills"><span><?php echo esc_html($r['family']);?></span><span>Factory-direct supply</span></div><p class="agx-lead"><?php echo esc_html($r['lead']);?></p>
  <?php if($r['features']):?><div class="agx-features"><?php foreach($r['features'] as $feature):?><div><span class="agx-feature-icon" aria-hidden="true">↗</span><strong><?php echo esc_html($feature[0]);?></strong><p><?php echo esc_html($feature[1]);?></p></div><?php endforeach;?></div><?php endif;?>
  <div class="agx-price" aria-live="polite"><?php echo $price?:'Price to be confirmed';?></div>
   <div class="agx-cart"><?php global $product; if($product && !$product->is_purchasable()){echo '<a class="agx-button agx-quote" href="'.esc_url(home_url('/online-quote/')).'">Request a quote <span>↗</span></a>';}else{woocommerce_template_single_add_to_cart();}?></div>
  <div class="agx-actions agx-secondary-actions"><a class="agx-button" href="<?php echo esc_url($help);?>"><?php echo $contact_url?'Get project help':'Review specifications';?> <span>↗</span></a><?php if($agx_pkg):?><a class="agx-button agx-button-line" href="#agx-package">See package details ↓</a><?php endif;?></div>
  <div class="agx-support"><strong>Planning a larger material package?</strong><span>Coordinate the product, finish and related components before ordering.</span></div></div>
 </section>
 <div class="agx-trust"><div><b>Factory direct</b><span>Aluglobus supply</span></div><div><b>Nationwide shipping</b><span>Material supply across the U.S.</span></div><div><b>Project support</b><span>Homeowners + trade professionals</span></div></div>
 <nav class="agx-nav" aria-label="Product sections"><a href="#agx-overview">Overview</a><?php if(class_exists('AGST_Media'))echo AGST_Media::nav($p->get_id(),class_exists('AGST_ElBuild')?(string)get_post_meta($p->get_id(),'_elementor_data',true):'');?><?php if($agx_pkg):?><a href="#agx-package">Package details</a><?php endif;?><a href="#agx-details">Specifications</a><a href="#agx-projects">Images</a><?php if($videos):?><a href="#agx-films">Videos</a><?php endif;?><?php if($r['faq']):?><a href="#agx-faq">Questions</a><?php endif;?><a href="#agx-configure">Choose options ↑</a></nav>
 <?php $agst_el=class_exists('AGST_ElBuild')?AGST_ElBuild::show($p->get_id()):null;$agst_has=$agst_el!==null&&(AGST_ElBuild::preview($p->get_id())||trim(wp_strip_all_tags($agst_el,true))!==''||stripos($agst_el,'<img')!==false||stripos($agst_el,'elementor-widget-video')!==false);?><?php if($agst_has):?><div class="agx-content agx-el" id="agx-overview"><?php echo $agst_el;?></div><?php elseif($content&&$agst_el===null):?><div class="agx-content" id="agx-overview"><?php echo AGST_Blocks::sections($content);?></div><?php endif;?>
 <?php if(!$agst_has&&($agst_el!==null||!$content)):?> <section class="agx-story agx-section" id="agx-overview"><div class="agx-story-copy"><p class="agx-eyebrow"><?php echo esc_html($r['family']);?></p><h2><?php echo esc_html($r['headline']);?></h2><p><?php echo esc_html($r['story']);?></p><a class="agx-text-link" href="<?php echo $agx_pkg?'#agx-package':'#agx-details';?>"><?php echo $agx_pkg?'Explore the package':'See the specifications';?> <span>↗</span></a></div><?php if($images):$im=$images[min(1,count($images)-1)];?><div class="agx-story-image"><button class="agx-zoom" data-full="<?php echo esc_url($im['url']);?>" aria-label="Enlarge product detail"><?php echo self::picture($im,$title);?></button><span class="agx-image-label">01 / Product detail</span></div><?php endif;?></section><?php endif;?>
 <?php if(class_exists('AGST_Media'))echo AGST_Media::section($p->get_id(),(string)$agst_el);?>
 <?php if($videos):?><section class="agx-section agx-film-section" id="agx-films"><div class="agx-section-heading"><div><p class="agx-eyebrow">See the system in action</p><h2>Watch the details come together.</h2></div><p>Product demonstrations and installation context. Check the listed package for the components supplied.</p></div><div class="agx-films"><?php foreach($videos as $video)echo self::video($video,$title);?></div></section><?php endif;?>
 <?php if($agx_pkg):?><section class="agx-section agx-light" id="agx-package"><div class="agx-section-heading"><div><p class="agx-eyebrow">Know your material package</p><h2><?php echo $r['inclusions']?'Inside the package.':'Plan the right combination.';?></h2></div><p>Separate the supplied components from the additional parts your installation requires.</p></div><div class="agx-package-grid"><div class="agx-package-card"><span class="agx-label">01 / <?php echo $r['inclusions']?'Listed inclusions':'Product configuration';?></span><h3><?php echo $r['inclusions']?'What comes with this listing':'Review before you order';?></h3><ul class="agx-checklist"><?php foreach(($r['inclusions']?:$r['specs']?:[$r['title']]) as $s):?><li><?php echo esc_html(preg_replace('/^\d+[.)]\s*/','',$s));?></li><?php endforeach;?></ul></div><div class="agx-package-card agx-package-alt"><span class="agx-label">02 / Complete the installation</span><h3><?php echo $r['post_options']?'Choose your mounting arrangement':'Check the adjoining components';?></h3><?php if($r['post_options']):?><ul class="agx-checklist"><?php foreach($r['post_options'] as $s):?><li><?php echo esc_html(preg_replace('/^\d+[.)]\s*/','',$s));?></li><?php endforeach;?></ul><p>Post options are separate choices. Confirm the selected mounting package and inclusions in your quotation.</p><?php else:?><p><?php echo esc_html($r['intro']??$r['story']);?></p><p>Photos and videos may show accessories or other products that are not included in this listing.</p><?php endif;?></div></div></section><?php endif;?>
 <section class="agx-section agx-spec-section" id="agx-details"><div><p class="agx-eyebrow">The technical details</p><h2>Specify with confidence.</h2><p>Check the dimensions, material and configuration against your project requirements.</p></div><div class="agx-specs"><?php foreach(($r['specs']?:[$r['title']]) as $i=>$s):?><div><span><?php echo str_pad((string)($i+1),2,'0',STR_PAD_LEFT);?></span><p><?php echo esc_html($s);?></p></div><?php endforeach;?></div></section>
 <?php if($supporting):?><section class="agx-section" id="agx-projects"><div class="agx-section-heading"><div><p class="agx-eyebrow">Explore every angle</p><h2>See more. Plan better.</h2></div><p>Product views, construction details and supporting images from this listing.</p></div><div class="agx-mosaic"><?php foreach($supporting as $i=>$im):?><figure><button class="agx-zoom" data-full="<?php echo esc_url($im['url']);?>" aria-label="Enlarge product image <?php echo $i+1;?>"><?php echo self::picture($im,$title);?></button><figcaption><span><?php echo str_pad((string)($i+1),2,'0',STR_PAD_LEFT);?></span><?php echo esc_html($im['alt']?:$title);?><span>↗</span></figcaption></figure><?php endforeach;?></div></section><?php endif;?>

 <?php if(class_exists('AGST_Related'))echo AGST_Related::section($p->get_id());?>
 <?php if($r['faq']):?><section class="agx-section agx-faq" id="agx-faq"><div><p class="agx-eyebrow">Before you build</p><h2>A few things worth knowing.</h2></div><div><?php foreach($r['faq'] as $qa):?><details><summary><?php echo esc_html($qa[0]);?><span aria-hidden="true">＋</span></summary><p><?php echo esc_html($qa[1]);?></p></details><?php endforeach;?></div></section><?php endif;?>
 <section class="agx-closing"><div><p class="agx-eyebrow">Make the next step a clear one</p><h2>Bring your project together.</h2><p>Choose the right configuration and coordinate the rest of your material package.</p></div><a class="agx-button" href="#agx-configure">Choose your options ↑</a></section>
 <dialog class="agx-lightbox" aria-label="Enlarged product image"><button class="agx-lightbox-close" aria-label="Close enlarged image">✕</button><img alt=""></dialog>
 </main>
 <?php }
}
AGST_Storefront::boot();

if (!defined('ABSPATH')) { exit; }
final class AGST_Content {
	/** Original product content (short + long) converted to clean sections, cached per source hash. */
	public static function get($p) {
		$id = $p->get_id();
		$empty = ['sections' => [], 'lead' => ''];
		$seo = (string) get_post_meta($id, '_agst_seo_html', true);
		if ($seo !== '') { return ['sections' => AGST_Convert::html_sections($seo), 'lead' => (string) get_post_meta($id, '_agst_seo_lead', true)]; }
		$key = get_post_meta($id, '_agst_key', true);
		$b = $key ? get_option('agst_backup_' . $key, false) : false;
		if ($b && !empty($b['new'])) { return $empty; }
		$use_backup = $b && version_compare((string) get_post_meta($id, '_agst_version', true), '0.3.1', '<');
		$long  = $use_backup ? $b['description'] : $p->get_description();
		$short = $use_backup ? $b['short'] : $p->get_short_description();
		$data  = $use_backup ? ($b['meta']['_elementor_data']['value'] ?? '') : get_post_meta($id, '_elementor_data', true);
		$mode  = $use_backup ? ($b['meta']['_elementor_edit_mode']['value'] ?? '') : get_post_meta($id, '_elementor_edit_mode', true);
		$raw   = is_string($data) ? $data : wp_json_encode($data);
		$hash  = md5(AGST_Convert::VERSION . '|' . $short . '|' . $long . '|' . $raw . '|' . $mode . '|' . home_url());
		$tkey  = 'agst_cc_' . $id;
		$cache = get_transient($tkey);
		if (is_array($cache) && ($cache['hash'] ?? '') === $hash) { return $cache['data']; }
		$tree = is_array($data) ? $data : json_decode((string) $data, true);
		$sections = [];
		if ($mode === 'builder' && is_array($tree) && $tree) { $sections = AGST_Convert::elementor_sections($tree); }
		if (!$sections && trim(wp_strip_all_tags((string) $long)) !== '') { $sections = AGST_Convert::html_sections(str_replace('\\n', "\n", (string) $long)); }
		$short_sections = trim(wp_strip_all_tags((string) $short)) !== '' ? AGST_Convert::html_sections(str_replace('\\n', "\n", (string) $short)) : [];
		// Lead: first substantial paragraph of the short description (shown in the purchase panel).
		$lead = '';
		foreach ($short_sections as $si => $s) {
			foreach ($s['blocks'] as $bi => $bl) {
				if (in_array($bl['t'], ['p', 'lead'], true) && mb_strlen(trim(wp_strip_all_tags($bl['html']))) > 60) {
					$lead = trim(html_entity_decode(wp_strip_all_tags(str_replace('<br>', ' ', $bl['html'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
					unset($short_sections[$si]['blocks'][$bi]);
					break 2;
				}
			}
		}
		// Drop short-description blocks whose copy already appears in the main content.
		$seen = [];
		foreach ($sections as $s) { foreach ($s['blocks'] as $bl) { $f = AGST_Convert::fingerprint($bl); if ($f !== '') { $seen[$f] = true; } } }
		$ss = [];
		foreach ($short_sections as $s) {
			$keep = [];
			foreach ($s['blocks'] as $bl) { $f = AGST_Convert::fingerprint($bl); if ($f !== '' && isset($seen[$f])) { continue; } $keep[] = $bl; }
			// A section left with only a heading/eyebrow is dropped.
			$substance = array_filter($keep, function ($x) { return !in_array($x['t'], ['eyebrow', 'h', 'title'], true); });
			if ($substance) { $ss[] = ['tone' => $s['tone'], 'blocks' => array_values($keep)]; }
		}
		$all = self::finish($id, array_merge($ss, $sections));
		$out = ['sections' => $all, 'lead' => $lead];
		set_transient($tkey, ['hash' => $hash, 'data' => $out], WEEK_IN_SECONDS);
		return $out;
	}

	/** Applies reviewed corrections, drops raw shortcodes and appends added copy. */
	public static function finish($id, $sections) {
		$sections = AGST_Fixes::apply($id, $sections);
		foreach ($sections as $si => $s) {
			$sections[$si]['blocks'] = array_values(array_filter($s['blocks'], function ($b) {
				return !(isset($b['html']) && preg_match('/^\s*\[[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]\s*$/i', html_entity_decode(strip_tags($b['html']), ENT_QUOTES, 'UTF-8')));
			}));
			if (!$sections[$si]['blocks']) { unset($sections[$si]); }
		}
		$sections = array_values($sections);
		$extra = AGST_Fixes::extra();
		if (!empty($extra[(int) $id])) { $sections = array_merge($sections, AGST_Convert::html_sections($extra[(int) $id])); }
		return $sections;
	}
}
/**
 * Reviewed content corrections for original product copy that does not match the product,
 * plus added copy for products whose original page was nearly empty.
 * Original stored content is never modified; corrections apply at render time.
 */
if (!defined('ABSPATH')) { exit; }

final class AGST_Fixes {

	/** Exact text corrections per product ID: [find, replace]. */
	public static function text() {
		return [
			// ALU20 4x6: the price list lists 15 ALU20 slats for this kit.
			38472 => [['Quantity: 16', 'Quantity: 15']],
			// ALU20 6x6: the price list lists 29 ALU20 slats for this kit.
			26856 => [
				['Quantity: 16', 'Quantity: 29'],
				['sixteen black ALU20 slats', 'twenty-nine black ALU20 slats'],
				['Sixteen 2″ × 3/4″ × 6′ ALU20 aluminum slats', 'Twenty-nine 2″ × 3/4″ × 6′ ALU20 aluminum slats'],
				['16 black ALU20 slats', '29 black ALU20 slats'],
				['sixteen black ALU20 horizontal slats', 'twenty-nine black ALU20 horizontal slats'],
			],
			// ALU40 4x6: the gate is 4 ft wide × 6 ft high and uses eight 8 ft ALU40 L.D slats.
			26832 => [
				['4′ high × 6′ wide', '4′ wide × 6′ high'],
				['4 feet high by 6 feet wide', '4 feet wide by 6 feet high'],
				['4-foot-high by 6-foot-wide', '4-foot-wide by 6-foot-high'],
				['4″ × 6′ ALU40 aluminum slats', '4″ × 8′ ALU40 aluminum slats'],
				['4-inch × 6-foot ALU40', '4-inch × 8-foot ALU40'],
				['The 6-foot-wide opening gives a broad pedestrian entry while the 4-foot height keeps the gate visually open.', 'The 4-foot-wide opening suits everyday pedestrian access while the 6-foot height adds privacy and screening.'],
			],
			60784 => [['At 8 feet long,', 'At 11 feet long,']],
			38089 => [['Attractive pyramid-style design', 'Clean, flat, low-profile design'], ['2 Aluminum Cap', '2″ Aluminum Cap']],
			60062 => [['3.15 Aluminum Cap', '3.15″ Aluminum Cap'], ['some users fit it onto 2″ square wood', 'some users fit it onto 3.15″ square wood']],
			60122 => [['Attractive pyramid-style design', 'Clean, flat, low-profile design'], ['the 2″ aluminum post cap', 'the 4″ aluminum post cap'], ['4 Aluminum Cap', '4″ Aluminum Cap'], ['some users fit it onto 2″ square wood', 'some users fit it onto 4″ square wood']],
			37475 => [['.0120 wall thickness', '.090 wall thickness']],
			37481 => [
				['What is this aluminum beam used for?', 'What is this aluminum post used for?'],
				['Is this beam rust-proof?', 'Is this post rust-proof?'],
				['Can the beam be cut or drilled?', 'Can the post be cut or drilled?'],
				['How is the 24FT beam shipped?', 'How is the 11 ft post shipped?'],
			],
			37483 => [['structural weakness.B', 'structural weakness.']],
			38126 => [['Durable 1 spacer maintains even slat alignment, even opens gaps', 'The durable 1″ spacer maintains even slat alignment, even open gaps'], ['The Spacer 1 ', 'The 1″ Spacer ']],
			38130 => [['2 spacer maintains consistent', 'The 2″ spacer maintains consistent'], ['The Spacer 2 ', 'The 2″ Spacer ']],
			26805 => [
				['ALU20 – 2" x 3/4 x 6FT CUSTOM COLOR (NEW)', 'ALU20 – 2″ x 3/4″ x 6FT Black slat'],
				['ALU20 – 2″ x 3/4 x 6FT CUSTOM COLOR (NEW)', 'ALU20 – 2″ x 3/4″ x 6FT Black slat'],
			],
			26649 => [
				['6-Roller Support System', '6″ Guide Roller'],
				['Multiple contact points eliminate wobbling and ensure stability.', 'A large 6″ powder-coated roller keeps the gate steady and eliminates wobbling.'],
				['Featuring a 6-roller support system, it provides superior stability compared to single-roller designs,', 'Featuring a large 6″ powder-coated roller, it provides steady guidance for the gate,'],
				['the six-piece design provides complete support', 'the 6″ roller provides complete support'],
				['Guide Roller 6 Pc Black', 'Guide Roller 6″ PC Black'],
			],
			26647 => [['Guide Roller 3 PC Black', 'Guide Roller 3″ PC Black']],
			26616 => [['Heavy Duty 5 Way Support Hinge', 'HD 5-1/4″ Double Wall Support Hinge']],
			32975 => [
				['5 ¼” wide × 3 ¼” thick', '5 ¼” wide × ¾” thick'],
				['What is CLAD120 woodgrain aluminum cladding used for?', 'What is CLAD120 Black aluminum cladding used for?'],
				['They offer the elegant look of white oak wood with the durability of aluminum.', 'They offer a clean, modern black finish with the durability of aluminum.'],
				['Is the woodgrain finish realistic?', 'How does the black finish look?'],
				['Yes. The powder-coated white oak finish mimics the natural texture and tone of real white oak, giving your project a refined, wood-like appearance without the downsides of real wood.', 'The powder-coated black finish gives your project a uniform, refined matte appearance with a crisp architectural line, without the upkeep of painted wood.'],
				['Will the white oak color fade over time?', 'Will the black finish fade over time?'],
				['Is CLAD120 better than real white oak wood siding?', 'Is CLAD120 better than real wood siding?'],
			],
			38274 => [
				['Low-close profile with 3/8″ (9 mm) gap between clips', 'Low-close profile with no gap between clips'],
				['Maintains a 3/8 inch spacing for a clean and consistent façade.', 'Closes with no gap between clips for a clean and consistent façade.'],
				['Align spacing using the built-in 3/8 inch gap for consistent placement.', 'Set each clip tight to the next; the close design leaves no gap between clips.'],
			],
			17272 => [['Clip-4 Low', 'Clip 4.7 Low']],
			17278 => [['Each extrusion typically measures 19 feet, reducing seams and ensuring smooth alignment.', 'The Low Click Rail is supplied in 10-foot lengths (2 × 10′), reducing seams and ensuring smooth alignment.']],
			25471 => [['19′ x 6m', '19′ / 6m']],
			25477 => [['19′ x 6m', '19′ / 6m']],
			25507 => [['19′ x 6m', '19′ / 6m']],
			38143 => [[' o ering ', ' offering ']],
			38152 => [[' o ers ', ' offers ']],
		];
	}

	/** Display-title corrections (the stored product name and URL are unchanged). */
	public static function title($id, $name) {
		$name = str_replace('EXTENTSION', 'EXTENSION', $name);
		if ((int) $id === 38152) { $name = 'Max Rail ™ 3/4 Lateral Profile'; }
		return $name;
	}

	/** Additional copy for products whose original page was nearly empty. */
	public static function extra() {
		$corner = function ($code, $for, $pack) {
			return '<section><p class="eyebrow">Click System trim hardware</p><h2>Aluminum corner ' . $code . ' for the Click System</h2>'
				. '<p>This 1.18″ × 1.18″ aluminum corner (' . $code . ') is the matching corner piece for Click System profile ' . $for . '. It closes and protects the corner where two cladding runs meet, so the finished wall shows a crisp, continuous edge instead of cut panel ends.</p>'
				. '<ul><li>Profile size: 1.18″ × 1.18″</li><li>Part code: ' . $code . ' — matched to ' . $for . '</li>' . ($pack ? '<li>Sold as a pack of 10 pieces</li>' : '') . '<li>Aluminum construction for interior and exterior cladding</li></ul></section>'
				. '<section><h2>Before you order</h2><ul><li>Confirm the Click System profile code on your cladding (' . $for . ') so the corner matches the panel.</li><li>Count every outside corner on the wall and order enough pieces for all rows.</li><li>Order the corners in the same finish as the cladding for a uniform look.</li></ul>'
				. '<details><summary>Which cladding does this corner fit?</summary><p>It is the matching corner for ' . $for . '. For other Click System profiles, choose the corner listed for that profile code.</p></details>'
				. '<details><summary>Is it suitable for outdoor use?</summary><p>Yes. Aluminum does not rust, which makes the corner suitable for exterior walls as well as interior feature walls.</p></details></section>';
		};
		$ext = function ($nogap) {
			return '<section><p class="eyebrow">Click System wall cladding</p><h2>Clip extension for a clean finished edge</h2>'
				. '<p>The Clip Extension for Finish is used at the top or side of a Click System wall to finish the last row of cladding. It extends the clip line so the final panel is held securely and the wall ends with a neat, straight edge' . ($nogap ? ' in no-gap (close) installations.' : '.') . '</p>'
				. '<ul><li>Length: 19 ft for long runs with fewer joints</li><li>Use: finishing the wall at the top or side</li>' . ($nogap ? '<li>Made for the no-gap (close) Click System</li>' : '<li>Made for Click System walls installed with a gap between clips</li>') . '<li>Aluminum profile for interior and exterior use</li></ul></section>'
				. '<section><h2>Installation tips</h2><ol><li>Install the regular clips and cladding rows first.</li><li>Cut the extension to the length of the finishing edge.</li><li>Fix the extension at the top or side edge, aligned with the clip line.</li><li>Click the final panel into place and check that the edge is straight.</li></ol>'
				. '<details><summary>When do I need the clip extension?</summary><p>Use it wherever the cladding ends against the top or the side of the wall and the last row needs a finished, supported edge.</p></details>'
				. '<details><summary>Can it be cut to length?</summary><p>Yes. The 19 ft profile can be cut on site with a saw suitable for aluminum.</p></details></section>';
		};
		return [
			38308 => $ext(false),
			38369 => $ext(true),
			38381 => '<section><p class="eyebrow">Click System wall cladding</p><h2>Back cover for 2″ Click System profiles</h2><p>The Back Cover closes the open back of 2″ Click System profiles, giving a finished appearance wherever the back of the cladding is visible — for example on free-standing screens, fences or wall toppers seen from both sides.</p><ul><li>Fits: 2″ Click System profiles</li><li>Length: 19 ft for long runs with fewer joints</li><li>Aluminum construction for interior and exterior use</li></ul></section><section><h2>Questions</h2><details><summary>When is a back cover needed?</summary><p>Use it when both faces of the cladding will be seen, so the reverse side looks as finished as the front.</p></details><details><summary>Can it be cut to length?</summary><p>Yes. The 19 ft profile can be cut on site with a saw suitable for aluminum.</p></details></section>',
			38384 => $corner('A-148', 'C-010', true),
			38387 => $corner('A-108', 'C-011 & C-012', false),
			38389 => $corner('A-048', 'C-014 & C-015', true),
			38391 => $corner('A-028', 'C-013', true),
			38143 => '<section><p class="eyebrow">AeroLouver fence system</p><h2>The post that carries the AeroLouver system</h2><p>This aluminum post (1.57″ W × 3.14″ H × 19′ L) is the structural base of the AeroLouver fencing system. It pairs with the AeroLouver spacers and profiles to hold the blades in line and keep the fence straight and rigid.</p><ul><li>Size: 1.57″ W × 3.14″ H × 19′ L</li><li>Pairs with AeroLouver spacers and profiles</li><li>Aluminum: corrosion-resistant and low-maintenance</li><li>Can be cut to the required post height</li></ul></section><section><h2>Planning your fence</h2><ol><li>Set out the fence line and post positions.</li><li>Cut the posts to height, allowing for the footing or base detail.</li><li>Install and plumb the posts.</li><li>Fit the spacers and AeroLouver profiles between the posts.</li></ol><details><summary>Can the 19 ft post be cut?</summary><p>Yes. Cut it on site with a saw suitable for aluminum to suit the fence height and footing detail.</p></details></section>',
		];
	}

	/** Cleans PDF-extraction artifacts in import-manifest copy (lists, specs, titles). */
	public static function clean_row($r) {
		$fix = function ($s) {
			if (!is_string($s)) { return $s; }
			$s = str_replace(['selected finish finish', 'EXTENTSION', 'we do we recommend', ' o ers ', ' o ering ', 'o ers ', 'o ering '], ['selected finish', 'EXTENSION', 'we recommend', ' offers ', ' offering ', 'offers ', 'offering '], $s);
			$s = preg_replace(['/\bnish\b/', '/\binll\b/', '/\bproles\b/', '/\bprole\b/'], ['finish', 'infill', 'profiles', 'profile'], $s);
			return $s;
		};
		foreach (['specs', 'inclusions', 'post_options', 'features', 'faq'] as $k) {
			if (empty($r[$k]) || !is_array($r[$k])) { continue; }
			$out = [];
			foreach ($r[$k] as $item) {
				if (is_array($item)) { $out[] = array_map($fix, $item); continue; }
				$item = $fix($item);
				$prev = $out ? $out[count($out) - 1] : null;
				// Re-join lines the PDF wrapped mid-sentence.
				if (is_string($prev) && (preg_match('/(?:\b(?:for|and|or|the|of|to|a|on|via|their|using|be|with|up to)|[-,:(])\s*$/', $prev) || preg_match('/^[a-z]/', $item) || strpos($item, 'Wood screw') === 0)) {
					$out[count($out) - 1] = rtrim($prev) . (substr(rtrim($prev), -1) === '-' ? '' : ' ') . ltrim($item);
					continue;
				}
				$out[] = $item;
			}
			$r[$k] = $out;
		}
		// "Item inclusions:" blocks inside specs belong in the inclusions list.
		if (!empty($r['specs']) && is_array($r['specs'])) {
			$specs = []; $inc = $r['inclusions'] ?? [];
			foreach ($r['specs'] as $sp) {
				if (is_string($sp) && preg_match('/^(?:Item|Kit)\s+inclusions:\s*(.*)$/i', $sp, $m)) {
					$rest = trim($m[1]);
					if (preg_match('/^\d+\./', $rest)) { foreach (preg_split('/\s(?=\d+\.\s)/', $rest) as $x) { $inc[] = trim($x); } }
					elseif ($rest !== '') { $inc[] = $rest; }
					continue;
				}
				if (is_string($sp) && preg_match('/^\d+\.\s/', $sp) && $inc && !preg_match('/Colors?:/i', end($specs) ?: '')) { $inc[] = $sp; continue; }
				$specs[] = $sp;
			}
			// "Colors:" followed by a numbered list becomes one readable line.
			$merged = [];
			foreach ($specs as $sp) {
				$last = $merged ? $merged[count($merged) - 1] : null;
				if (is_string($sp) && is_string($last) && preg_match('/Colors?:/i', $last) && preg_match('/^\d+\.\s*(.+)$/', $sp, $mm)) {
					$merged[count($merged) - 1] = preg_replace('/(Colors?:)\s*\d+\.\s*/i', '$1 ', $last) . ', ' . trim($mm[1]);
					continue;
				}
				$merged[] = $sp;
			}
			$r['specs'] = array_map(function ($x) { return is_string($x) ? str_replace('self-closing option we recommend for self-closing function purchasing', 'self-closing option. For a true self-closing function we recommend purchasing', $x) : $x; }, $merged);
			$r['inclusions'] = $inc;
		}
		foreach (['lead', 'story', 'intro', 'headline', 'title', 'display_title'] as $k) { if (isset($r[$k])) { $r[$k] = $fix($r[$k]); } }
		$key = $r['key'] ?? '';
		if ($key === 'P053-087' && !empty($r['specs'])) { $r['specs'] = str_replace('Usage: 2 in. Square Post', 'Usage: 3.15 in. Square Post', $r['specs']); }
		if ($key === 'P100-254' && !empty($r['specs'])) { $r['specs'] = str_replace('Finish angle for High click channel + lo', 'Finish angle for High click channel + High clip', $r['specs']); }
		return $r;
	}

	/** Applies text corrections to every string in a section list. */
	public static function apply($id, $sections) {
		$all = self::text();
		$pairs = $all[(int) $id] ?? [];
		if (!$pairs) { return $sections; }
		$find = []; $repl = [];
		foreach ($pairs as $p) {
			$find[] = $p[0]; $repl[] = $p[1];
			$e0 = htmlspecialchars($p[0], ENT_QUOTES, 'UTF-8');
			if ($e0 !== $p[0]) { $find[] = $e0; $repl[] = htmlspecialchars($p[1], ENT_QUOTES, 'UTF-8'); }
		}
		$walk = function ($x) use (&$walk, $find, $repl) {
			if (is_string($x)) { return str_replace($find, $repl, $x); }
			if (is_array($x)) { foreach ($x as $k => $v) { if (in_array($k, ['src', 'full', 'href', 'poster', 'id', 'kind', 't'], true)) { continue; } $x[$k] = $walk($v); } }
			return $x;
		};
		return $walk($sections);
	}

	/** Existing-category assignments for newly created products (no new categories). */
	public static function categories($key, $current) {
		$G = 'Aluminum Gates'; $DIY = 'Aluminum Gates > Build your own DIY Gates';
		$IP = 'Individual Profiles'; $POST = 'Individual Profiles > Posts'; $SLAT = 'Individual Profiles > Slats';
		$HW = 'Hardware & Accessories'; $PER = 'Pergola';
		$map = [
			'P010-010' => [$IP, $POST, $DIY], 'P011-011' => [$IP, $POST, $DIY], 'P012-012' => [$IP, $POST, $DIY], 'P012-013' => [$IP, $POST, $DIY],
			'P015-021' => [$IP, $SLAT, $DIY], 'P017-027' => [$IP, $SLAT, $DIY],
			'P028-040' => [$G, 'Aluminum Gates > Aluminum Gates - DIY Aluminum Gate Kit', 'Aluminum Gates > Aluminum Gates - Alu50-60 T&G', 'Gates', 'Gates > Pedestrian Single Swing Gate'],
			'P031-043' => [$G, 'Aluminum Gates > Aluminum Gates - DIY Aluminum Gate Kit', 'Aluminum Gates > Aluminum Gates - Alu50-60 T&G', 'Gates', 'Gates > Pedestrian Single Swing Gate'],
			'P035-046' => [$IP, $POST, 'Aluminum Fences > Aluminum Fences - DIY Fence Kit Post'], 'P037-049' => [$IP, $POST, 'Aluminum Fences > Aluminum Fences - DIY Fence Kit Post'],
			'P040-052' => [$IP, $POST, 'Aluminum Fences > Aluminum Fences - DIY Fence Kit Post'], 'P041-055' => [$IP, $POST, 'Aluminum Fences > Aluminum Fences - DIY Fence Kit Post'], 'P041-057' => [$IP, $POST, 'Aluminum Fences > Aluminum Fences - DIY Fence Kit Post'],
			'P045-071' => ['Aluminum Fences', 'Aluminum Fences > Aluminum Fences - Alu40', 'Aluminum Fences > Aluminum Fences - DIY Fence Kit Post', 'Fences'],
			'P047-072' => ['Aluminum Fences', 'Aluminum Fences > Aluminum Fences - Alu40', 'Aluminum Fences > Aluminum Fences - DIY Fence Kit Post', 'Fences'],
			'P051-080' => [$IP, $POST], 'P052-083' => [$IP, $POST], 'P054-093' => [$IP, $POST], 'P052-086' => [$IP, $POST, $HW],
			'P055-096' => [$IP, $POST], 'P056-099' => [$IP, $POST], 'P056-102' => [$IP, $POST],
			'P059-109' => [$IP, $SLAT, $DIY], 'P060-112' => [$IP, $SLAT, $DIY], 'P063-119' => [$IP, $SLAT, $DIY], 'P063-120' => [$IP, $SLAT, $DIY], 'P061-115' => [$IP, $SLAT],
			'P084-187' => [$HW, $DIY], 'P085-188' => [$HW, $DIY], 'P085-192' => [$HW, $DIY], 'P087-199' => [$HW, $DIY], 'P084-185' => [$HW, $DIY], 'P084-186' => [$HW, $DIY],
			'P088-200' => [$IP, $DIY], 'P088-201' => [$IP, $DIY], 'P088-202' => [$IP, $DIY],
			'P089-203' => [$G, $DIY, 'Gates'], 'P089-204' => [$G, $DIY, 'Gates'], 'P090-205' => [$G, $DIY, 'Gates'],
			'P096-228' => ['Wall cladding'],
		];
		if (isset($map[$key])) { return implode(', ', $map[$key]); }
		// Normalise dash variants to the existing category names.
		return str_replace(['–', '—'], '-', (string) $current);
	}

	/** Manifest row as used by the importer and the page renderer. */
	// Price-list completion (2026-09-30): rows held for review are imported so the shop carries every price-list item.
	static function promote($r) {
		static $fam = ['P015-018' => ['P015-019', 'P015-020'], 'P016-024' => ['P017-025', 'P017-026'], 'P023-034' => ['P024-035', 'P024-036'], 'P025-037' => ['P026-038', 'P027-039'], 'P048-075' => ['P049-076', 'P050-077']];
		static $single = ['P043-064', 'P043-065', 'P044-069', 'P064-125', 'P065-126', 'P055-097', 'P056-098', 'P093-212'];
		static $raw = null; static $child = null;
		if ($raw === null) { $raw = []; foreach ((array) json_decode((string) file_get_contents(__DIR__ . '/manifest.json'), true) as $x) { $raw[$x['key']] = $x; } $child = []; foreach ($fam as $p => $c) { foreach ($c as $k) { $child[$k] = $p; } } }
		$k = $r['key'] ?? '';
		$strip = function ($t) { return trim(preg_replace('/\s*-\s*(Black|Bronze|White)(\s*-\s*COMING SOON)?\s*$/i', '', (string) $t)); };
		$color = function ($t) { return preg_match('/-\s*(Black|Bronze|White)(\s*-\s*COMING SOON)?\s*$/i', (string) $t, $m) ? ucfirst(strtolower($m[1])) : 'Black'; };
		if (isset($fam[$k])) {
			$v = [];
			foreach (array_merge([$k], $fam[$k]) as $vk) { $x = $raw[$vk] ?? null; if (!$x) { continue; } $c = $color($x['source_title'] ?? $x['title']); $v[] = ['key' => $vk, 'color' => $c, 'price' => $x['price'], 'asset_files' => $x['asset_files'] ?? [], 'coming_soon' => !empty($x['coming_soon']), 'sku' => 'AGST-' . $vk . '-' . strtoupper($c)]; }
			$t = $strip($r['title']);
			$r['action'] = 'create'; $r['reason'] = ''; $r['variants'] = $v; $r['title'] = $t; $r['display_title'] = $t; $r['slug'] = sanitize_title($t); $r['source_keys'] = array_column($v, 'key');
			$r['notes'] = ['One variable product with ' . implode(', ', array_column($v, 'color')) . '.'];
		} elseif (isset($child[$k])) {
			$r['action'] = 'variation'; $r['reason'] = 'Color variation under ' . $child[$k] . '; import the parent row only.';
		} elseif (in_array($k, $single, true)) {
			$r['action'] = 'create'; $r['reason'] = ''; $r['slug'] = sanitize_title($r['title']);
		}
		return $r;
	}
	static function safe_categories($r) {
		$k = $r['key'] ?? '';
		$promoted = ['P015-018', 'P016-024', 'P023-034', 'P025-037', 'P048-075', 'P043-064', 'P043-065', 'P044-069', 'P064-125', 'P065-126', 'P055-097', 'P056-098', 'P093-212', 'P028-040', 'P031-043'];
		if (!in_array($k, $promoted, true)) { return $r; }
		$fam = strtolower((string) ($r['family'] ?? ''));
		$r['categories'] = (strpos($fam, 'gate') !== false && strpos($fam, 'fence') === false) || in_array($k, ['P015-018', 'P016-024', 'P023-034', 'P025-037', 'P028-040', 'P031-043'], true) ? 'Aluminum Gates' : (strpos($fam, 'cladding') !== false ? 'Wall cladding' : 'Aluminum Fences');
		return $r;
	}
	public static function prepare_row($r) {
		$r = self::clean_row($r);
		$r = self::promote($r);
		if (($r['action'] ?? '') === 'create') { $r['categories'] = self::categories($r['key'] ?? '', $r['categories'] ?? ''); }
		if (($r['key'] ?? '') === 'P096-228') { $r['title'] = 'Y-Corner Piece'; $r['display_title'] = 'Y-Corner Piece'; }
		$r = self::safe_categories($r);
		return $r;
	}
}

add_action('wp_ajax_agst_sync_categories', function () {
	check_ajax_referer('agst-catalog', 'nonce');
	try {
		AGST_Catalog::guard();
		$done = [];
		foreach (AGST_Catalog::manifest() as $r) {
			if (($r['action'] ?? '') !== 'create') { continue; }
			$id = AGST_Catalog::prior($r['key']);
			if (!$id) { continue; }
			$ids = array_map('intval', array_map(['AGST_Catalog', 'category'], array_map('trim', explode(',', $r['categories']))));
			wp_set_object_terms($id, $ids, 'product_cat');
			wc_delete_product_transients($id);
			$done[] = $id . ': ' . $r['categories'];
		}
		wp_send_json_success($done);
	} catch (Throwable $e) {
		wp_send_json_error($e->getMessage());
	}
});
/**
 * Converts legacy product content (Elementor JSON, custom HTML widgets, WooCommerce HTML)
 * into clean, style-free content blocks. Words are kept exactly; legacy CSS/JS is dropped.
 */
if (!defined('ABSPATH')) { exit; }

final class AGST_Convert {

	const VERSION = '1.0.1';

	const INLINE = ['a','abbr','b','bdi','bdo','br','cite','code','del','dfn','em','font','i','ins','kbd','label','mark','q','s','samp','small','span','strong','sub','sup','time','u','var','wbr'];
	const SKIP   = ['style','script','noscript','template','svg','form','input','select','textarea','option','hr','link','meta','head','title','canvas','object','embed','map','picture>source'];
	const KEEP_INLINE = ['strong','b','em','i','u','sup','sub','br','a','code','small','mark','s','del'];

	/** Hosts whose absolute URLs are rewritten to site-relative paths (works on staging and live). */
	public static $own_hosts = ['aluglobusfence.com', 'www.aluglobusfence.com', 'globusgates.online', 'www.globusgates.online'];

	/* ------------------------------------------------------------------ URLs */

	public static function url($u) {
		$u = trim(html_entity_decode((string) $u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		$u = str_replace('\\/', '/', $u);
		if ($u === '') { return ''; }
		if (preg_match('~^(?:https?:)?//([^/:?#]+)(.*)$~i', $u, $m)) {
			if (in_array(strtolower($m[1]), self::$own_hosts, true)) {
				$rest = $m[2];
				return ($rest === '' ? '/' : ($rest[0] === '/' ? $rest : '/' . $rest));
			}
			return (strpos($u, '//') === 0 ? 'https:' . $u : $u);
		}
		return $u;
	}

	public static function is_image_url($u) {
		return (bool) preg_match('~\.(?:jpe?g|png|gif|webp|avif)(?:\?.*)?$~i', (string) $u);
	}

	/** Parses a YouTube / Vimeo / file URL into a video descriptor, or null. */
	public static function video_from_url($u, $poster = '', $title = '') {
		$u = self::url($u);
		if ($u === '') { return null; }
		if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/|watch\?(?:.*&)?v=|v/))([A-Za-z0-9_-]{11})~', $u, $m)) {
			return ['t' => 'video', 'kind' => 'youtube', 'id' => $m[1], 'poster' => $poster ? self::url($poster) : '', 'title' => $title];
		}
		if (preg_match('~vimeo\.com/(?:video/)?(\d{5,})~', $u, $m)) {
			return ['t' => 'video', 'kind' => 'vimeo', 'id' => $m[1], 'poster' => $poster ? self::url($poster) : '', 'title' => $title];
		}
		if (preg_match('~\.(?:mp4|webm|mov|m4v|ogv)(?:\?.*)?$~i', $u)) {
			return ['t' => 'video', 'kind' => 'file', 'src' => $u, 'poster' => $poster ? self::url($poster) : '', 'title' => $title];
		}
		return null;
	}

	/* ------------------------------------------------------------------ DOM */

	public static function dom($html) {
		$html = (string) $html;
		$doc = new DOMDocument('1.0', 'UTF-8');
		$prev = libxml_use_internal_errors(true);
		$doc->loadHTML('<?xml encoding="UTF-8"><html><body><div id="agst-conv-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_COMPACT);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		return $doc->getElementById('agst-conv-root');
	}

	private static function cls(DOMElement $e) {
		return strtolower(' ' . preg_replace('/\s+/', ' ', $e->getAttribute('class')) . ' ');
	}

	private static function has_cls(DOMElement $e, $re) {
		return (bool) preg_match($re, self::cls($e));
	}

	private static function norm($s) {
		$s = str_replace(["\xc2\xa0", "\r"], [' ', ''], $s);
		return preg_replace('/[ \t\n]+/u', ' ', $s);
	}

	private static function text(DOMNode $n) {
		return trim(self::norm($n->textContent));
	}

	private static function skip(DOMNode $n) {
		if (!($n instanceof DOMElement)) { return false; }
		$name = strtolower($n->nodeName);
		if (in_array($name, self::SKIP, true)) { return true; }
		if ($name === 'dialog') { return true; }
		// Old in-page section menus ("Specs · System · FAQ …") are replaced by the page's own navigation.
		if ($name === 'nav') { return true; }
		if ($name === 'a' && strpos(ltrim($n->getAttribute('href')), '#') === 0 && !self::first_img($n)) {
			$p = $n->parentNode; $txt = false;
			if ($p) { foreach ($p->childNodes as $x) { if ($x instanceof DOMText && trim($x->nodeValue) !== '') { $txt = true; break; } } }
			if (!$txt) { return true; }
		}
		$kids = self::element_children($n);
		if (count($kids) >= 2 && trim(implode('', array_map(function ($x) { return $x instanceof DOMText ? $x->nodeValue : ''; }, iterator_to_array($n->childNodes)))) === '') {
			$jump = 0;
			foreach ($kids as $k) { if (strtolower($k->nodeName) === 'a' && strpos(ltrim($k->getAttribute('href')), '#') === 0) { $jump++; } }
			if ($jump === count($kids)) { return true; }
		}
		if ($n->getAttribute('aria-hidden') === 'true' && self::text($n) === '' ) { return true; }
		if ($n->getAttribute('hidden') !== '' && $n->hasAttribute('hidden')) { return true; }
		$c = self::cls($n);
		// Lightbox / modal shells (not the links that open them).
		if (preg_match('/[\s_-](?:lightbox|modal|lb)(?:[\s]|$)|lightbox-(?:dialog|overlay|inner|wrap|image|caption|close|prev|next|nav|stage)|(?:^|\s)[a-z0-9]*-?lightbox\s/', $c) && !preg_match('/lightbox-(?:link|trigger|open|item)/', $c)) {
			return true;
		}
		if (preg_match('/(?:^|[\s_-])(?:play|play-symbol|play-icon)(?:\s|$)/', $c) && self::text($n) !== '' && mb_strlen(self::text($n)) <= 2) { return true; }
		if (preg_match('/(?:^|[\s_-])(?:hero-colorbar|colorbar|swatch-dots)(?:\s|$)/', $c) && self::text($n) === '') { return true; }
		// Pure-CSS illustrations (dimension arrows, drawn frames) carry no product content.
		if (preg_match('/(?:^|\s)[a-z0-9_-]*(?:dimension-[hv]|dim-[hv]|frame-stage|frame-art|hero-art|art-stage|css-art|drawing-stage)(?:\s|$)/', $c) && $n->getElementsByTagName('img')->length === 0) { return true; }
		return false;
	}

	private static function is_block_el(DOMNode $n) {
		if (!($n instanceof DOMElement)) { return false; }
		$name = strtolower($n->nodeName);
		if (in_array($name, self::INLINE, true)) {
			if ($name === 'a' && self::is_button($n)) { return true; }
			// span/a/label containing block content behaves as a block container
			foreach ($n->getElementsByTagName('*') as $d) {
				if (!in_array(strtolower($d->nodeName), self::INLINE, true) && strtolower($d->nodeName) !== 'img') { return true; }
			}
			foreach ($n->getElementsByTagName('img') as $d) { return true; }
			return false;
		}
		return true;
	}

	private static function is_button(DOMElement $a) {
		if (strtolower($a->nodeName) !== 'a') { return false; }
		$c = self::cls($a);
		if (preg_match('/(?:^|[\s_-])(?:btn|button|cta|elementor-button)(?:[\s_-]|$)/', $c)) { return true; }
		$p = $a->parentNode;
		if ($p instanceof DOMElement && preg_match('/(?:btn|button|cta|actions)[-_]?(?:row|group|wrap|s)?(?:\s|$)/', self::cls($p))) { return true; }
		return false;
	}

	/** Sanitised inline HTML of a node's children: only simple formatting and links survive. */
	public static function inline(DOMNode $n) {
		$out = '';
		foreach ($n->childNodes as $c) {
			if ($c instanceof DOMText) {
				$out .= htmlspecialchars(self::norm($c->nodeValue), ENT_QUOTES, 'UTF-8');
				continue;
			}
			if (!($c instanceof DOMElement) || self::skip($c)) { continue; }
			$name = strtolower($c->nodeName);
			if ($name === 'br') { $out .= '<br>'; continue; }
			if ($name === 'img') { continue; }
			$inner = self::inline($c);
			if (in_array($name, ['strong','b'], true)) { $out .= trim($inner) === '' ? $inner : '<strong>' . $inner . '</strong>'; continue; }
			if (in_array($name, ['em','i'], true)) { $out .= trim($inner) === '' ? $inner : '<em>' . $inner . '</em>'; continue; }
			if (in_array($name, ['sup','sub','u','small','code','s','del','mark'], true)) { $out .= '<' . $name . '>' . $inner . '</' . $name . '>'; continue; }
			if ($name === 'a') {
				$href = self::url($c->getAttribute('href'));
				if ($href !== '' && $href[0] !== '#' && stripos($href, 'javascript:') !== 0 && trim(strip_tags($inner)) !== '') {
					$out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $inner . '</a>';
				} else {
					$out .= $inner;
				}
				continue;
			}
			if (in_array($name, ['p','div','li','h1','h2','h3','h4','h5','h6','tr','dt','dd','section','article','ul','ol','table','blockquote','figcaption'], true)) {
				$inner = trim($inner);
				if ($inner !== '') { $out .= ($out !== '' && substr($out, -4) !== '<br>' ? '<br>' : '') . $inner . '<br>'; }
				continue;
			}
			$out .= $inner;
		}
		$out = preg_replace('/(?:<br>\s*){2,}/', '<br>', $out);
		$out = preg_replace('/^(?:\s|<br>)+|(?:\s|<br>)+$/', '', $out);
		return trim(preg_replace('/ {2,}/', ' ', $out));
	}

	/* ------------------------------------------------------------ media nodes */

	private static function first_img(DOMElement $e) {
		foreach ($e->getElementsByTagName('img') as $img) { return $img; }
		return null;
	}

	private static function img_block(DOMElement $img, $full = '') {
		$src = $img->getAttribute('src');
		if (!$src || strpos($src, 'data:') === 0) { $src = $img->getAttribute('data-src') ?: $img->getAttribute('data-lazy-src'); }
		$src = self::url($src);
		if ($src === '' || strpos($src, 'data:') === 0) { return null; }
		if (preg_match('~(?:img\.youtube\.com|i\.ytimg\.com)/~', $src)) { return null; } // thumbnails belong to videos
		$w = (int) $img->getAttribute('width');
		$h = (int) $img->getAttribute('height');
		if (($w && $w < 40) || ($h && $h < 40)) { return null; } // icons
		return ['t' => 'img', 'src' => $src, 'alt' => trim(self::norm($img->getAttribute('alt'))), 'full' => ($full && self::is_image_url($full)) ? self::url($full) : '', 'caption' => ''];
	}

	/** Detects any of the many legacy video markups; returns a video block or null. */
	private static function video_node(DOMElement $e) {
		$name = strtolower($e->nodeName);
		$poster = '';
		$img = self::first_img($e);
		if ($img) { $poster = $img->getAttribute('src'); }
		$title = trim($e->getAttribute('aria-label') ?: $e->getAttribute('data-video-title') ?: $e->getAttribute('title'));
		foreach (['data-video-src', 'data-youtube-url', 'data-video', 'data-embed', 'data-src'] as $a) {
			if ($e->hasAttribute($a) && ($v = self::video_from_url($e->getAttribute($a), $poster, $title))) { return $v; }
		}
		if ($e->hasAttribute('data-youtube') && preg_match('/^[A-Za-z0-9_-]{11}$/', $e->getAttribute('data-youtube'))) {
			return ['t' => 'video', 'kind' => 'youtube', 'id' => $e->getAttribute('data-youtube'), 'poster' => self::url($poster), 'title' => $title];
		}
		if ($e->hasAttribute('data-vimeo') && preg_match('/^\d{5,}$/', $e->getAttribute('data-vimeo'))) {
			return ['t' => 'video', 'kind' => 'vimeo', 'id' => $e->getAttribute('data-vimeo'), 'poster' => self::url($poster), 'title' => $title];
		}
		if ($name === 'iframe') {
			return self::video_from_url($e->getAttribute('src') ?: $e->getAttribute('data-src'), '', $e->getAttribute('title'));
		}
		if ($name === 'video') {
			$src = $e->getAttribute('src');
			if (!$src) { foreach ($e->getElementsByTagName('source') as $s) { $src = $s->getAttribute('src'); if ($src) { break; } } }
			return $src ? self::video_from_url($src, $e->getAttribute('poster'), $title) : null;
		}
		return null;
	}

	/* ------------------------------------------------------------ main walker */

	/** Converts an HTML fragment to a list of sections: [['tone'=>..,'blocks'=>[..]], ...]. */
	public static function html_sections($html) {
		$root = self::dom($html);
		if (!$root) { return []; }
		// Unwrap single wrappers down to the level holding the page sections.
		$node = $root;
		for ($i = 0; $i < 6; $i++) {
			$kids = self::element_children($node);
			if (count($kids) === 1 && !in_array(strtolower($kids[0]->nodeName), ['p','ul','ol','table','img','h1','h2','h3','h4','h5','h6'], true) && self::direct_text($node) === '') {
				$node = $kids[0];
				continue;
			}
			break;
		}
		$kids = self::element_children($node);
		$sectioned = 0;
		foreach ($kids as $k) {
			if (strtolower($k->nodeName) === 'section' || self::has_cls($k, '/(?:^|[\s_-])section(?:\s|$)|-section\b/')) { $sectioned++; }
		}
		if ($sectioned >= 2) {
			$sections = [];
			$loose = [];
			foreach ($kids as $k) {
				if (self::skip($k)) { continue; }
				$is_sec = strtolower($k->nodeName) === 'section' || self::has_cls($k, '/(?:^|[\s_-])section(?:\s|$)|-section\b/');
				if (!$is_sec) { $loose = array_merge($loose, self::blocks($k)); continue; }
				if ($loose) { $sections[] = ['tone' => '', 'blocks' => $loose]; $loose = []; }
				$b = self::blocks($k);
				if ($b) { $sections[] = ['tone' => self::has_cls($k, '/dark|black|charcoal|video-band|cta|buy-banner/') ? 'dark' : '', 'blocks' => $b]; }
			}
			if ($loose) { $sections[] = ['tone' => '', 'blocks' => $loose]; }
			return $sections;
		}
		return self::sectionize(self::blocks($node));
	}

	private static function element_children(DOMNode $n) {
		$out = [];
		foreach ($n->childNodes as $c) {
			if ($c instanceof DOMElement && !self::skip($c)) { $out[] = $c; }
		}
		return $out;
	}

	private static function direct_text(DOMNode $n) {
		$t = '';
		foreach ($n->childNodes as $c) { if ($c instanceof DOMText) { $t .= $c->nodeValue; } }
		return trim(self::norm($t));
	}

	/** Splits a flat block list into sections at each level-2 heading. */
	public static function sectionize(array $blocks) {
		$sections = [];
		$cur = [];
		$n = count($blocks);
		for ($i = 0; $i < $n; $i++) {
			$b = $blocks[$i];
			$starts = ($b['t'] === 'h' && $b['l'] <= 2) || ($b['t'] === 'eyebrow' && isset($blocks[$i + 1]) && $blocks[$i + 1]['t'] === 'h' && $blocks[$i + 1]['l'] <= 2);
			if ($starts && $cur && !(count($cur) === 1 && $cur[0]['t'] === 'eyebrow')) {
				$sections[] = ['tone' => '', 'blocks' => $cur];
				$cur = [];
			}
			$cur[] = $b;
			if ($b['t'] === 'eyebrow' && $starts) { $cur[] = $blocks[++$i]; }
		}
		if ($cur) { $sections[] = ['tone' => '', 'blocks' => $cur]; }
		return $sections;
	}

	/** Converts the children of a node into blocks. */
	public static function blocks(DOMNode $n) {
		$out = [];
		$kids = [];
		foreach ($n->childNodes as $c) {
			if ($c instanceof DOMElement && self::skip($c)) { continue; }
			if ($c instanceof DOMText && trim(self::norm($c->nodeValue)) === '') { continue; }
			if ($c instanceof DOMComment) { continue; }
			$kids[] = $c;
		}
		$has_text = false;
		foreach ($kids as $c) { if ($c instanceof DOMText) { $has_text = true; break; } }

		// Repeated-card detection among contiguous block-element children (text breaks a run).
		$grid = null;
		$seg = [];
		foreach ($kids as $c) {
			if ($c instanceof DOMElement && self::is_block_el($c)) { $seg[] = $c; continue; }
			$g = self::detect_grid($seg);
			if ($g && (!$grid || count($g['items']) > count($grid['items']))) { $grid = $g; }
			$seg = [];
		}
		$g = self::detect_grid($seg);
		if ($g && (!$grid || count($g['items']) > count($grid['items']))) { $grid = $g; }

		$run = '';           // accumulating inline run (mixed text)
		$flush = function () use (&$run, &$out) {
			$h = trim(preg_replace('/^(?:\s|<br>)+|(?:\s|<br>)+$/', '', $run));
			if ($h !== '' && trim(strip_tags($h)) !== '') { $out[] = ['t' => 'p', 'html' => $h]; }
			$run = '';
		};
		$i = 0;
		$count = count($kids);
		while ($i < $count) {
			$c = $kids[$i];
			if ($grid && $c === $grid['items'][0]) {
				$flush();
				$gb = self::grid_block($grid['items']);
				if ($gb['t'] === '__flat') { foreach ($gb['blocks'] as $x) { $out[] = $x; } } else { $out[] = $gb; }
				$i += count($grid['items']);
				// grid items are contiguous by construction
				continue;
			}
			if ($c instanceof DOMText) {
				$run .= htmlspecialchars(self::norm($c->nodeValue), ENT_QUOTES, 'UTF-8');
				$i++;
				continue;
			}
			/** @var DOMElement $c */
			if (!self::is_block_el($c)) {
				$name = strtolower($c->nodeName);
				if ($has_text) {
					$tmp = $c->ownerDocument->createElement('span');
					$tmp->appendChild($c->cloneNode(true));
					$run .= ($name === 'br' ? '<br>' : self::inline($tmp));
				} elseif ($name !== 'br') {
					$h = self::inline_of($c);
					if ($h !== '' && trim(strip_tags($h)) !== '') {
						$isTitle = in_array($name, ['strong','b'], true) || self::has_cls($c, '/title|name|label|heading|head(?:\s|$)|strong/');
						$isEyebrow = self::has_cls($c, '/eyebrow|kicker|overline|pretitle|tagline|badge|chip|pill/');
						$out[] = ['t' => $isEyebrow ? 'eyebrow' : ($isTitle ? 'title' : 'p'), 'html' => $h];
					}
				}
				$i++;
				continue;
			}
			$flush();
			foreach (self::element($c) as $b) { $out[] = $b; }
			$i++;
		}
		$flush();
		return self::merge($out);
	}

	private static function inline_of(DOMElement $c) {
		$tmp = $c->ownerDocument->createElement('span');
		$tmp->appendChild($c->cloneNode(true));
		return self::inline($tmp);
	}

	/** Converts one block-level element. */
	private static function element(DOMElement $e) {
		$name = strtolower($e->nodeName);
		if ($v = self::video_node($e)) { return [$v]; }
		switch ($name) {
			case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
				$h = self::inline($e);
				if (trim(strip_tags($h)) === '') { return self::media_inside($e); }
				$lvl = (int) $name[1];
				$out = [['t' => 'h', 'l' => $lvl <= 2 ? 2 : ($lvl === 3 ? 3 : 4), 'html' => $h]];
				return array_merge($out, self::media_inside($e));
			case 'p':
				$h = self::inline($e);
				$media = self::media_inside($e);
				if (trim(strip_tags($h)) === '') { return $media; }
				$t = self::has_cls($e, '/eyebrow|kicker|overline|pretitle/') ? 'eyebrow' : (self::has_cls($e, '/(?:^|[\s_-])(?:lead|intro|subtitle|deck)(?:\s|$)/') ? 'lead' : 'p');
				return array_merge([['t' => $t, 'html' => $h]], $media);
			case 'ul': case 'ol':
				$items = [];
				foreach (self::element_children($e) as $li) {
					if (strtolower($li->nodeName) !== 'li') { continue; }
					$h = self::inline($li);
					if (trim(strip_tags($h)) !== '') { $items[] = $h; }
				}
				$media = self::media_inside($e);
				return array_merge($items ? [['t' => 'list', 'ordered' => $name === 'ol', 'items' => $items]] : [], $media);
			case 'table':
				return self::table($e);
			case 'dl':
				return self::dl($e);
			case 'details':
				return [self::details($e)];
			case 'img':
				$b = self::img_block($e);
				return $b ? [$b] : [];
			case 'figure':
				return self::figure($e);
			case 'blockquote':
				$h = self::inline($e);
				return $h !== '' ? [['t' => 'quote', 'html' => $h, 'name' => '']] : [];
			case 'a':
				return self::anchor($e);
			case 'button':
				$t = self::text($e);
				if ($t === '' || mb_strlen($t) <= 2 || preg_match('/^(?:play|close|prev|previous|next|zoom|open|expand|×|✕|‹|›|▶)\b/i', $t)) {
					return self::media_inside($e);
				}
				return array_merge([['t' => 'title', 'html' => self::inline($e)]], self::media_inside($e));
			case 'iframe': case 'video':
				return [];
			case 'dt':
				return [['t' => 'title', 'html' => self::inline($e)]];
			case 'dd':
				return [['t' => 'p', 'html' => self::inline($e)]];
			case 'li':
				$h = self::inline($e);
				return $h !== '' ? [['t' => 'list', 'ordered' => false, 'items' => [$h]]] : [];
		}
		// Spec rows: <div class="row"><dt/><dd/></div> or label/value pairs.
		if ($pair = self::pair($e)) { return [['t' => 'specs', 'rows' => [$pair]]]; }
		// Generic container.
		$b = self::blocks($e);
		if (self::has_cls($e, '/eyebrow|kicker|overline|pretitle/') && count($b) === 1 && in_array($b[0]['t'], ['p','title'], true)) {
			$b[0]['t'] = 'eyebrow';
		}
		if (self::has_cls($e, '/(?:^|[\s_-])(?:note|notice|callout|alert|highlight|warning|tip|disclaimer)(?:\s|$)/') && $b) {
			return [['t' => 'note', 'blocks' => $b]];
		}
		return $b;
	}

	private static function pair(DOMElement $e) {
		$kids = self::element_children($e);
		if (count($kids) !== 2) { return null; }
		$a = strtolower($kids[0]->nodeName); $b = strtolower($kids[1]->nodeName);
		$isdl = ($a === 'dt' && $b === 'dd');
		$islv = self::has_cls($kids[0], '/label|key|name|term|spec-l|(?:^|[\s_-])k(?:\s|$)/') && self::has_cls($kids[1], '/value|val|desc|detail|spec-v|(?:^|[\s_-])v(?:\s|$)/');
		if (!$isdl && !$islv) { return null; }
		$l = self::inline($kids[0]);
		$vb = self::blocks($kids[1]);
		$v = self::blocks_to_inline($vb);
		if (trim(strip_tags($l)) === '' && trim(strip_tags($v)) === '') { return null; }
		return [$l, $v];
	}

	private static function blocks_to_inline(array $blocks) {
		$parts = [];
		foreach ($blocks as $b) {
			if (isset($b['html'])) { $parts[] = $b['html']; }
			elseif ($b['t'] === 'list') { $parts[] = implode('<br>', array_map(function ($x) { return '• ' . $x; }, $b['items'])); }
			elseif ($b['t'] === 'specs') { foreach ($b['rows'] as $r) { $parts[] = $r[0] . ': ' . $r[1]; } }
		}
		return implode('<br>', array_filter($parts, function ($x) { return trim(strip_tags($x)) !== ''; }));
	}

	private static function media_inside(DOMElement $e) {
		$out = [];
		foreach ($e->getElementsByTagName('img') as $img) {
			$b = self::img_block($img);
			if ($b) { $out[] = $b; }
		}
		foreach ($e->getElementsByTagName('iframe') as $f) {
			$v = self::video_node($f);
			if ($v) { $out[] = $v; }
		}
		return $out;
	}

	private static function anchor(DOMElement $a) {
		$href = self::url($a->getAttribute('href'));
		$img = self::first_img($a);
		if ($img) {
			$b = self::img_block($img, $href);
			$rest = [];
			foreach (self::blocks($a) as $x) { if ($x['t'] !== 'img') { $rest[] = $x; } }
			if ($b && count($rest) === 1 && in_array($rest[0]['t'], ['p','title'], true) && mb_strlen(strip_tags($rest[0]['html'])) < 140) {
				$b['caption'] = $rest[0]['html'];
				$rest = [];
			}
			return array_merge($b ? [$b] : [], $rest);
		}
		if ($v = self::video_from_url($href)) {
			$t = self::text($a);
			$v['title'] = $t;
			return [$v];
		}
		if (self::is_image_url($href) && self::text($a) === '') {
			return [['t' => 'img', 'src' => $href, 'alt' => trim($a->getAttribute('aria-label')), 'full' => '', 'caption' => '']];
		}
		$text = self::inline($a);
		$text = trim(strip_tags($text, '<strong><em><b><i>'));
		if ($text === '') { return []; }
		if ($href === '' || $href[0] === '#' || stripos($href, 'javascript:') === 0) {
			return []; // in-page navigation controls of the old layout
		}
		if (self::is_button($a) || self::is_block_el($a)) {
			return [['t' => 'btn', 'href' => $href, 'html' => $text]];
		}
		return [['t' => 'p', 'html' => '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $text . '</a>']];
	}

	private static function figure(DOMElement $f) {
		$out = [];
		$cap = '';
		foreach (self::element_children($f) as $c) {
			if (strtolower($c->nodeName) === 'figcaption') { $cap = self::inline($c); continue; }
			foreach (self::element($c) as $b) { $out[] = $b; }
		}
		$imgs = array_keys(array_filter($out, function ($b) { return $b['t'] === 'img'; }));
		if ($cap !== '') {
			if (count($imgs) === 1) { $out[$imgs[0]]['caption'] = $cap; }
			else { $out[] = ['t' => 'p', 'html' => $cap]; }
		}
		return $out;
	}

	private static function details(DOMElement $d) {
		$q = '';
		$answer = [];
		foreach (self::element_children($d) as $c) {
			if (strtolower($c->nodeName) === 'summary') { $q = self::inline($c); continue; }
			foreach (self::element($c) as $b) { $answer[] = $b; }
		}
		foreach ($d->childNodes as $c) {
			if ($c instanceof DOMText && trim(self::norm($c->nodeValue)) !== '') { $answer[] = ['t' => 'p', 'html' => htmlspecialchars(trim(self::norm($c->nodeValue)), ENT_QUOTES, 'UTF-8')]; }
		}
		$q = preg_replace('/\s*[+＋−–-]\s*$/u', '', $q);
		return ['t' => 'faq', 'items' => [['q' => $q, 'a' => $answer]]];
	}

	private static function dl(DOMElement $dl) {
		$rows = [];
		$label = null;
		$walk = function (DOMElement $n) use (&$walk, &$rows, &$label) {
			foreach (self::element_children($n) as $c) {
				$nm = strtolower($c->nodeName);
				if ($nm === 'dt') { $label = self::inline($c); }
				elseif ($nm === 'dd') { $rows[] = [$label ?? '', self::inline($c)]; $label = null; }
				else { $walk($c); }
			}
		};
		$walk($dl);
		return $rows ? [['t' => 'specs', 'rows' => $rows]] : [];
	}

	private static function table(DOMElement $t) {
		$rows = [];
		$head = [];
		foreach ($t->getElementsByTagName('tr') as $tr) {
			$cells = [];
			$is_head = true;
			foreach (self::element_children($tr) as $cell) {
				$nm = strtolower($cell->nodeName);
				if (!in_array($nm, ['td','th'], true)) { continue; }
				if ($nm === 'td') { $is_head = false; }
				$cells[] = self::inline($cell);
			}
			if (!$cells) { continue; }
			if ($is_head && !$rows && !$head) { $head = $cells; continue; }
			$rows[] = $cells;
		}
		$media = self::media_inside($t);
		if (!$rows && !$head) { return $media; }
		$maxc = 0;
		foreach ($rows as $r) { $maxc = max($maxc, count($r)); }
		// Two-column label/value tables become specification rows.
		if ($maxc === 2 && (!$head || count($head) === 2)) {
			$spec = [];
			foreach ($rows as $r) { $spec[] = [$r[0] ?? '', $r[1] ?? '']; }
			$out = [];
			if ($head && trim(strip_tags(implode('', $head))) !== '') { $out[] = ['t' => 'title', 'html' => implode(' — ', array_filter($head))]; }
			$out[] = ['t' => 'specs', 'rows' => $spec];
			return array_merge($out, $media);
		}
		// Single-column tables are really lists (often with a header like "Key Features").
		if ($maxc <= 1) {
			$items = array_values(array_filter(array_map(function ($r) { return $r[0] ?? ''; }, $rows), function ($x) { return trim(strip_tags($x)) !== ''; }));
			$out = [];
			if ($head && trim(strip_tags($head[0])) !== '') { $out[] = ['t' => 'title', 'html' => $head[0]]; }
			if ($items) { $out[] = ['t' => 'list', 'ordered' => false, 'items' => $items]; }
			return array_merge($out, $media);
		}
		return array_merge([['t' => 'table', 'head' => $head, 'rows' => $rows]], $media);
	}

	/* --------------------------------------------------------- grid handling */

	private static function sig(DOMElement $e) {
		$out = [];
		foreach (preg_split('/\s+/', strtolower(trim($e->getAttribute('class')))) as $c) {
			if ($c === '' || preg_match('/^(?:shell|container|wrap|wrapper|inner|row|col|cols|grid|flex|d-flex|reveal|fade|in-view|visible|active|is-[a-z-]+|has-[a-z-]+|js-[a-z-]+|light|dark|soft|alt|reverse|wide|full|elementor-[a-z0-9-]+|e-[a-z0-9-]+)$/', $c)) { continue; }
			$out[] = preg_replace('/--[a-z0-9-]+$|-\d+$/', '', $c);
		}
		return ['tag' => strtolower($e->nodeName), 'cls' => array_values(array_unique($out))];
	}

	private static function same_sig($a, $b) {
		if ($a['tag'] !== $b['tag']) { return false; }
		if (!$a['cls'] && !$b['cls']) { return true; }
		return (bool) array_intersect($a['cls'], $b['cls']);
	}

	/** Finds the longest contiguous run of >=2 same-signature compound siblings. */
	private static function detect_grid(array $els) {
		$n = count($els);
		if ($n < 2) { return null; }
		$best = null;
		$i = 0;
		while ($i < $n) {
			$s = self::sig($els[$i]);
			$j = $i + 1;
			while ($j < $n && self::same_sig(self::sig($els[$j]), $s)) { $j++; }
			$len = $j - $i;
			$tag = strtolower($els[$i]->nodeName);
			if ($len >= 2 && !in_array($tag, ['p','h1','h2','h3','h4','h5','h6','ul','ol','table','img','dl','details','br','a','figure','li','dt','dd','iframe','video','blockquote','button'], true)) {
				if (!$best || $len > count($best['items'])) { $best = ['items' => array_slice($els, $i, $len)]; }
			}
			if ($len >= 2 && in_array($tag, ['figure','a'], true)) {
				// runs of figures / image links become galleries
				$allimg = true;
				foreach (array_slice($els, $i, $len) as $x) { if (!self::first_img($x)) { $allimg = false; break; } }
				if ($allimg && (!$best || $len > count($best['items']))) { $best = ['items' => array_slice($els, $i, $len)]; }
			}
			$i = $j;
		}
		if (!$best) { return null; }
		// Items must also be adjacent in the DOM (ignoring whitespace/skipped nodes).
		return $best;
	}

	private static function shape(array $blocks) {
		$t = array_map(function ($b) { return $b['t']; }, $blocks);
		return implode(',', array_slice($t, 0, 2)) . (in_array('btn', $t, true) || in_array('btns', $t, true) ? '+btn' : '');
	}

	private static function grid_block(array $items) {
		$cards = [];
		foreach ($items as $it) {
			$name = strtolower($it->nodeName);
			$b = ($name === 'figure') ? self::figure($it) : (($name === 'a') ? self::anchor($it) : (self::pair($it) ? [['t' => 'specs', 'rows' => [self::pair($it)]]] : self::blocks($it)));
			// Leading number badges ("01") become the card index.
			$num = '';
			if ($b && in_array($b[0]['t'], ['p','title','eyebrow'], true) && preg_match('/^\s*(\d{1,2})\s*$/', strip_tags($b[0]['html']), $m)) {
				$num = $m[1];
				array_shift($b);
			}
			if ($b) { $cards[] = ['num' => $num, 'blocks' => $b]; }
		}
		// Two structurally different items are a side-by-side layout, not a card grid.
		if (count($cards) === 2 && self::shape($cards[0]['blocks']) !== self::shape($cards[1]['blocks'])) {
			return ['t' => '__flat', 'blocks' => array_merge($cards[0]['blocks'], $cards[1]['blocks'])];
		}
		// All cards are specs rows -> one spec table.
		$allspec = true;
		foreach ($cards as $c) { if (count($c['blocks']) !== 1 || $c['blocks'][0]['t'] !== 'specs') { $allspec = false; break; } }
		if ($allspec && $cards) {
			$rows = [];
			foreach ($cards as $c) { foreach ($c['blocks'][0]['rows'] as $r) { $rows[] = $r; } }
			return ['t' => 'specs', 'rows' => $rows];
		}
		// All cards are a single image (optionally captioned) -> gallery.
		$allimg = true;
		foreach ($cards as $c) {
			$imgs = array_filter($c['blocks'], function ($x) { return $x['t'] === 'img'; });
			$txt = array_filter($c['blocks'], function ($x) { return $x['t'] !== 'img'; });
			if (count($imgs) !== 1 || count($txt) > 2) { $allimg = false; break; }
		}
		if ($allimg && $cards) {
			$images = [];
			foreach ($cards as $c) {
				$img = null; $cap = [];
				foreach ($c['blocks'] as $x) { if ($x['t'] === 'img') { $img = $x; } else { $cap[] = self::blocks_to_inline([$x]); } }
				if ($img) {
					if (!$img['caption'] && $cap) { $img['caption'] = implode(' · ', array_filter($cap)); }
					$images[] = $img;
				}
			}
			return ['t' => 'gallery', 'images' => $images];
		}
		// All cards are single short text -> checklist.
		$alltext = true;
		foreach ($cards as $c) {
			if (count($c['blocks']) !== 1 || !in_array($c['blocks'][0]['t'], ['p','title','lead'], true)) { $alltext = false; break; }
		}
		if ($alltext && $cards) {
			return ['t' => 'list', 'ordered' => false, 'check' => true, 'items' => array_map(function ($c) { return $c['blocks'][0]['html']; }, $cards)];
		}
		// All cards are videos -> video grid.
		$allvid = true;
		$vids = [];
		foreach ($cards as $c) {
			$v = array_values(array_filter($c['blocks'], function ($x) { return $x['t'] === 'video'; }));
			if (count($v) !== 1) { $allvid = false; break; }
			$cap = [];
			foreach ($c['blocks'] as $x) { if ($x['t'] !== 'video' && $x['t'] !== 'img') { $cap[] = strip_tags(self::blocks_to_inline([$x])); } }
			if ($cap) { $v[0]['caption'] = implode(' · ', array_filter($cap)); }
			$vids[] = $v[0];
		}
		if ($allvid && $vids) { return ['t' => 'videos', 'items' => $vids]; }
		// All cards are FAQ -> single FAQ block
		$allfaq = true;
		foreach ($cards as $c) { foreach ($c['blocks'] as $x) { if ($x['t'] !== 'faq') { $allfaq = false; break 2; } } }
		if ($allfaq && $cards) {
			$items = [];
			foreach ($cards as $c) { foreach ($c['blocks'] as $x) { $items = array_merge($items, $x['items']); } }
			return ['t' => 'faq', 'items' => $items];
		}
		return ['t' => 'cards', 'items' => $cards];
	}

	/** Merges adjacent compatible blocks (lists of images -> gallery, specs, faqs, videos). */
	public static function merge(array $blocks) {
		$out = [];
		foreach ($blocks as $b) {
			$last = $out ? $out[count($out) - 1] : null;
			if ($last && $b['t'] === 'faq' && $last['t'] === 'faq') { $out[count($out) - 1]['items'] = array_merge($last['items'], $b['items']); continue; }
			if ($last && $b['t'] === 'specs' && $last['t'] === 'specs') { $out[count($out) - 1]['rows'] = array_merge($last['rows'], $b['rows']); continue; }
			if ($last && $b['t'] === 'img' && in_array($last['t'], ['img','gallery'], true)) {
				$prev = $last['t'] === 'img' ? [$last] : $last['images'];
				$prev[] = $b;
				$out[count($out) - 1] = ['t' => 'gallery', 'images' => $prev];
				continue;
			}
			if ($last && $b['t'] === 'video' && in_array($last['t'], ['video','videos'], true)) {
				$prev = $last['t'] === 'video' ? [$last] : $last['items'];
				$prev[] = $b;
				$out[count($out) - 1] = ['t' => 'videos', 'items' => $prev];
				continue;
			}
			if ($last && $b['t'] === 'btn' && in_array($last['t'], ['btn','btns'], true)) {
				$prev = $last['t'] === 'btn' ? [$last] : $last['items'];
				$prev[] = $b;
				$out[count($out) - 1] = ['t' => 'btns', 'items' => $prev];
				continue;
			}
			$out[] = $b;
		}
		return $out;
	}

	/* ------------------------------------------------------------ Elementor */

	/** Converts Elementor JSON elements into sections. */
	public static function elementor_sections(array $els) {
		$blocks = self::el_blocks($els, $html_sections);
		$sections = [];
		// If an HTML widget produced its own sections, keep those boundaries.
		$flat = [];
		foreach ($blocks as $b) {
			if ($b['t'] === '__sections') {
				if ($flat) { $sections = array_merge($sections, self::sectionize(self::merge($flat))); $flat = []; }
				$sections = array_merge($sections, $b['sections']);
				continue;
			}
			$flat[] = $b;
		}
		if ($flat) { $sections = array_merge($sections, self::sectionize(self::merge($flat))); }
		return $sections;
	}

	private static function el_blocks(array $els, &$unused = null) {
		$out = [];
		foreach ($els as $e) {
			if (!is_array($e)) { continue; }
			$s = isset($e['settings']) && is_array($e['settings']) ? $e['settings'] : [];
			$type = $e['widgetType'] ?? ($e['elType'] ?? '');
			$kids = isset($e['elements']) && is_array($e['elements']) ? $e['elements'] : [];
			switch ($type) {
				case 'heading':
					$tag = strtolower($s['header_size'] ?? 'h2');
					$lvl = preg_match('/^h([1-6])$/', $tag, $m) ? (int) $m[1] : 2;
					$root = self::dom($s['title'] ?? '');
					$h = $root ? self::inline($root) : '';
					if (trim(strip_tags($h)) !== '') {
						if (!empty($s['link']['url'])) { $h = '<a href="' . htmlspecialchars(self::url($s['link']['url']), ENT_QUOTES, 'UTF-8') . '">' . strip_tags($h, '<strong><em>') . '</a>'; }
						$out[] = ($lvl >= 5 || in_array($tag, ['div','span','p'], true)) ? ['t' => 'title', 'html' => $h] : ['t' => 'h', 'l' => $lvl <= 2 ? 2 : ($lvl === 3 ? 3 : 4), 'html' => $h];
					}
					break;
				case 'text-editor':
					$root = self::dom($s['editor'] ?? '');
					if ($root) { foreach (self::blocks($root) as $b) { $out[] = $b; } }
					break;
				case 'html':
					$secs = self::html_sections($s['html'] ?? '');
					if (count($secs) > 1) { $out[] = ['t' => '__sections', 'sections' => $secs]; }
					elseif ($secs) { foreach ($secs[0]['blocks'] as $b) { $out[] = $b; } }
					break;
				case 'nested-accordion':
					$items = [];
					$titles = $s['items'] ?? [];
					foreach ($kids as $i => $child) {
						$q = $titles[$i]['item_title'] ?? '';
						$root = self::dom($q);
						$items[] = ['q' => $root ? self::inline($root) : htmlspecialchars($q), 'a' => self::merge(self::flatten_sections(self::el_blocks($child['elements'] ?? [])))];
					}
					if ($items) { $out[] = ['t' => 'faq', 'items' => $items]; }
					break;
				case 'accordion': case 'toggle':
					$items = [];
					foreach (($s['tabs'] ?? []) as $tab) {
						$root = self::dom($tab['tab_content'] ?? '');
						$items[] = ['q' => htmlspecialchars($tab['tab_title'] ?? ''), 'a' => $root ? self::blocks($root) : []];
					}
					if ($items) { $out[] = ['t' => 'faq', 'items' => $items]; }
					break;
				case 'tbay-testimonials-tab':
					$cards = [];
					foreach (($s['tabs'] ?? []) as $tab) {
						$root = self::dom($tab['tab_content'] ?? '');
						$b = $root ? self::blocks($root) : [];
						array_unshift($b, ['t' => 'title', 'html' => htmlspecialchars($tab['tab_name'] ?? '', ENT_QUOTES, 'UTF-8')]);
						$cards[] = ['num' => '', 'blocks' => $b];
					}
					if ($cards) {
						foreach ($cards as $i => $c) { $cards[$i]['num'] = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); }
						$out[] = ['t' => 'cards', 'items' => $cards, 'steps' => true];
					}
					break;
				case 'video':
					$vt = $s['video_type'] ?? 'youtube';
					$v = null;
					if ($vt === 'youtube') { $v = self::video_from_url($s['youtube_url'] ?? 'https://www.youtube.com/watch?v=XHOmBV4js_E'); }
					elseif ($vt === 'vimeo') { $v = self::video_from_url($s['vimeo_url'] ?? ''); }
					elseif ($vt === 'hosted') { $v = self::video_from_url($s['hosted_url']['url'] ?? ($s['external_url']['url'] ?? '')); }
					if ($v) {
						if (!empty($s['image_overlay']['url'])) { $v['poster'] = self::url($s['image_overlay']['url']); }
						$out[] = $v;
					}
					break;
				case 'image':
					if (!empty($s['image']['url'])) {
						$cap = '';
						if (($s['caption_source'] ?? '') === 'custom' && !empty($s['caption'])) { $cap = htmlspecialchars($s['caption'], ENT_QUOTES, 'UTF-8'); }
						$alt = $s['image']['alt'] ?? '';
						$out[] = ['t' => 'img', 'src' => self::url($s['image']['url']), 'alt' => $alt, 'full' => '', 'caption' => $cap, 'id' => (int) ($s['image']['id'] ?? 0)];
					}
					break;
				case 'image-carousel': case 'image-gallery': case 'gallery':
					$list = $s['carousel'] ?? ($s['wp_gallery'] ?? ($s['gallery'] ?? []));
					$imgs = [];
					foreach ($list as $im) { if (!empty($im['url'])) { $imgs[] = ['t' => 'img', 'src' => self::url($im['url']), 'alt' => '', 'full' => '', 'caption' => '', 'id' => (int) ($im['id'] ?? 0)]; } }
					if ($imgs) { $out[] = ['t' => 'gallery', 'images' => $imgs]; }
					break;
				case 'icon-list':
					$items = [];
					foreach (($s['icon_list'] ?? []) as $it) {
						$root = self::dom($it['text'] ?? '');
						$h = $root ? self::inline($root) : '';
						if (!empty($it['link']['url']) && trim(strip_tags($h)) !== '') { $h = '<a href="' . htmlspecialchars(self::url($it['link']['url']), ENT_QUOTES, 'UTF-8') . '">' . strip_tags($h, '<strong><em>') . '</a>'; }
						if (trim(strip_tags($h)) !== '') { $items[] = $h; }
					}
					if ($items) { $out[] = ['t' => 'list', 'ordered' => false, 'check' => true, 'items' => $items]; }
					break;
				case 'icon-box': case 'image-box':
					$b = [];
					if ($type === 'image-box' && !empty($s['image']['url'])) { $b[] = ['t' => 'img', 'src' => self::url($s['image']['url']), 'alt' => '', 'full' => '', 'caption' => '']; }
					if (!empty($s['title_text'])) { $r = self::dom($s['title_text']); $b[] = ['t' => 'title', 'html' => $r ? self::inline($r) : '']; }
					if (!empty($s['description_text'])) { $r = self::dom(nl2br($s['description_text'])); $b[] = ['t' => 'p', 'html' => $r ? self::inline($r) : '']; }
					if ($b) { $out[] = ['t' => 'cards', 'items' => [['num' => '', 'blocks' => $b]]]; }
					break;
				case 'button':
					$txt = trim($s['text'] ?? '');
					$href = self::url($s['link']['url'] ?? '');
					if ($txt !== '' && $href !== '' && $href[0] !== '#') { $out[] = ['t' => 'btn', 'href' => $href, 'html' => htmlspecialchars($txt, ENT_QUOTES, 'UTF-8')]; }
					break;
				case 'nested-carousel': case 'nested-tabs':
					$cards = [];
					$titles = $s['tabs'] ?? ($s['carousel_items'] ?? []);
					foreach ($kids as $i => $child) {
						$b = self::merge(self::flatten_sections(self::el_blocks($child['elements'] ?? [])));
						$title = $titles[$i]['tab_title'] ?? '';
						if ($title !== '') { array_unshift($b, ['t' => 'title', 'html' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8')]); }
						if ($b) { $cards[] = ['num' => '', 'blocks' => $b]; }
					}
					if ($cards) { $out[] = ['t' => 'cards', 'items' => $cards]; }
					break;
				case 'text-path': case 'spacer': case 'divider': case 'google_maps': case 'menu-anchor': case 'shortcode':
					break;
				default:
					// containers, sections, columns and unknown widgets: recurse
					if ($kids) {
						$inner = self::el_blocks($kids);
						// Horizontal containers whose children are compound (image + text) become cards.
						foreach ($inner as $b) { $out[] = $b; }
					} elseif (!empty($s['editor']) || !empty($s['html'])) {
						$root = self::dom($s['editor'] ?? $s['html']);
						if ($root) { foreach (self::blocks($root) as $b) { $out[] = $b; } }
					}
			}
		}
		return $out;
	}

	private static function flatten_sections(array $blocks) {
		$out = [];
		foreach ($blocks as $b) {
			if ($b['t'] === '__sections') { foreach ($b['sections'] as $s) { foreach ($s['blocks'] as $x) { $out[] = $x; } } }
			else { $out[] = $b; }
		}
		return $out;
	}

	/* ------------------------------------------------------------ utilities */

	/** Plain text of all words in a section list (for QA / word-preservation checks). */
	public static function words(array $sections) {
		$t = '';
		$walk = function ($x) use (&$walk, &$t) {
			if (is_array($x)) { foreach ($x as $k => $v) { if (in_array($k, ['src','full','href','id','kind','poster','t','l','tone','num'], true)) { continue; } $walk($v); } }
			elseif (is_string($x)) { $t .= ' ' . html_entity_decode(strip_tags(str_replace('<br>', ' ', $x)), ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
		};
		$walk($sections);
		return trim(preg_replace('/\s+/u', ' ', $t));
	}

	/** Every image and video referenced by a section list. */
	public static function media(array $sections) {
		$m = ['img' => [], 'video' => []];
		$walk = function ($x) use (&$walk, &$m) {
			if (!is_array($x)) { return; }
			if (isset($x['t']) && $x['t'] === 'img' && !empty($x['src'])) { $m['img'][] = $x['src']; }
			if (isset($x['t']) && $x['t'] === 'video') { $m['video'][] = $x['kind'] . ':' . ($x['kind'] === 'file' ? preg_replace('~\?.*$~', '', $x['src']) : $x['id']); }
			foreach ($x as $v) { if (is_array($v)) { $walk($v); } }
		};
		$walk($sections);
		return $m;
	}

	/** Text fingerprint of one block, used to drop repeated copy (e.g. short description repeated in the body). */
	public static function fingerprint($b) {
		$t = self::words([['blocks' => [$b]]]);
		if (mb_strlen($t) < 25) { return ''; }
		return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($t));
	}
}
/**
 * Renders AGST_Convert sections with the storefront design system.
 * Output uses only agx-c-* classes styled in storefront.css; no inline styles, no legacy CSS.
 */
if (!defined('ABSPATH')) { exit; }

final class AGST_Blocks {

	private static function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

	/** Allows only the inline markup produced by AGST_Convert::inline(). */
	public static function inline($html) {
		$html = (string) $html;
		$html = preg_replace('~<(?!/?(?:strong|em|u|sup|sub|small|code|s|del|mark|br|a)\b)[^>]*>~i', '', $html);
		$html = preg_replace_callback('~<a\b[^>]*>~i', function ($m) {
			if (preg_match('~href="([^"]*)"~i', $m[0], $h)) {
				$href = html_entity_decode($h[1], ENT_QUOTES, 'UTF-8');
				if (preg_match('~^\s*javascript:~i', $href)) { return '<a>'; }
				$ext = preg_match('~^https?://~i', $href);
				return '<a href="' . self::e($href) . '"' . ($ext ? ' rel="noopener" target="_blank"' : '') . '>';
			}
			return '<a>';
		}, $html);
		return $html;
	}

	private static function text($html) {
		return trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}

	/* ------------------------------------------------------------ media */

	public static function image($im, $cls = '', $eager = false) {
		$src = $im['src'];
		$full = !empty($im['full']) ? $im['full'] : $src;
		$alt = trim((string) ($im['alt'] ?? ''));
		$cap = trim((string) ($im['caption'] ?? ''));
		if ($alt === '' && $cap !== '') { $alt = self::text($cap); }
		$h = '<figure class="agx-c-figure ' . $cls . '"><button type="button" class="agx-zoom agx-c-zoom" data-full="' . self::e($full) . '" aria-label="' . self::e('Enlarge image' . ($alt ? ': ' . $alt : '')) . '">';
		$h .= '<img src="' . self::e($src) . '" alt="' . self::e($alt) . '" ' . ($eager ? 'fetchpriority="high"' : 'loading="lazy"') . ' decoding="async"></button>';
		if ($cap !== '') { $h .= '<figcaption>' . self::inline($cap) . '</figcaption>'; }
		return $h . '</figure>';
	}

	public static function video($v) {
		$title = trim((string) ($v['title'] ?? '')) ?: 'Product video';
		$cap = trim((string) ($v['caption'] ?? ''));
		$h = '<div class="agx-video agx-c-video">';
		if ($v['kind'] === 'youtube') {
			$poster = $v['poster'] ?: 'https://img.youtube.com/vi/' . $v['id'] . '/hqdefault.jpg';
			$h .= '<button type="button" class="agx-play" data-embed="' . self::e('https://www.youtube-nocookie.com/embed/' . $v['id'] . '?autoplay=1&rel=0') . '" aria-label="' . self::e('Play: ' . $title) . '"><img src="' . self::e($poster) . '" alt="" loading="lazy"><span class="agx-play-symbol" aria-hidden="true">▶</span><span class="agx-play-label">Watch video</span></button>';
		} elseif ($v['kind'] === 'vimeo') {
			$h .= '<button type="button" class="agx-play" data-embed="' . self::e('https://player.vimeo.com/video/' . $v['id'] . '?autoplay=1&title=0&byline=0&portrait=0') . '" aria-label="' . self::e('Play: ' . $title) . '">' . ($v['poster'] ? '<img src="' . self::e($v['poster']) . '" alt="" loading="lazy">' : '') . '<span class="agx-play-symbol" aria-hidden="true">▶</span><span class="agx-play-label">Watch video</span></button>';
		} else {
			$h .= '<video controls playsinline preload="none"' . ($v['poster'] ? ' poster="' . self::e($v['poster']) . '"' : '') . ' src="' . self::e($v['src']) . '" aria-label="' . self::e($title) . '"></video>';
		}
		if ($cap !== '') { $h .= '<p class="agx-c-video-cap">' . self::e($cap) . '</p>'; }
		return $h . '</div>';
	}

	public static function video_key($v) {
		return $v['kind'] . ':' . ($v['kind'] === 'file' ? preg_replace('~\?.*$~', '', $v['src']) : $v['id']);
	}

	/* ------------------------------------------------------------ blocks */

	public static function block($b, $ctx = []) {
		switch ($b['t']) {
			case 'eyebrow':
				return '<p class="agx-eyebrow agx-c-eyebrow">' . self::inline($b['html']) . '</p>';
			case 'h':
				$l = (int) $b['l'];
				$tag = $l <= 2 ? 'h2' : ($l === 3 ? 'h3' : 'h4');
				return '<' . $tag . ' class="agx-c-h agx-c-h' . $l . '">' . self::inline($b['html']) . '</' . $tag . '>';
			case 'title':
				return '<h4 class="agx-c-title">' . self::inline($b['html']) . '</h4>';
			case 'lead':
				return '<p class="agx-c-lead">' . self::inline($b['html']) . '</p>';
			case 'p':
				return '<p class="agx-c-p">' . self::inline($b['html']) . '</p>';
			case 'list':
				$tag = !empty($b['ordered']) ? 'ol' : 'ul';
				$cls = !empty($b['ordered']) ? 'agx-c-ol' : 'agx-c-ul';
				$h = '<' . $tag . ' class="' . $cls . '">';
				foreach ($b['items'] as $it) { $h .= '<li>' . self::inline($it) . '</li>'; }
				return $h . '</' . $tag . '>';
			case 'specs':
				$h = '<dl class="agx-c-specs">';
				foreach ($b['rows'] as $r) {
					$l = trim((string) $r[0]);
					$v = trim((string) $r[1]);
					if ($l === '' && $v === '') { continue; }
					$h .= '<div class="agx-c-spec' . ($l === '' ? ' agx-c-spec-wide' : '') . '">' . ($l !== '' ? '<dt>' . self::inline(rtrim($l, ':')) . '</dt>' : '') . '<dd>' . self::inline($v) . '</dd></div>';
				}
				return $h . '</dl>';
			case 'table':
				$h = '<div class="agx-c-table" role="region" tabindex="0" aria-label="Product table"><table>';
				if (!empty($b['head'])) { $h .= '<thead><tr>'; foreach ($b['head'] as $c) { $h .= '<th scope="col">' . self::inline($c) . '</th>'; } $h .= '</tr></thead>'; }
				$h .= '<tbody>';
				foreach ($b['rows'] as $r) { $h .= '<tr>'; foreach ($r as $c) { $h .= '<td>' . self::inline($c) . '</td>'; } $h .= '</tr>'; }
				return $h . '</tbody></table></div>';
			case 'faq':
				$h = '<div class="agx-c-faq">';
				foreach ($b['items'] as $it) {
					$h .= '<details><summary><span>' . self::inline($it['q']) . '</span><span class="agx-c-faq-icon" aria-hidden="true">＋</span></summary><div class="agx-c-faq-a">' . self::blocks($it['a'], $ctx) . '</div></details>';
				}
				return $h . '</div>';
			case 'img':
				return '<div class="agx-c-single">' . self::image($b) . '</div>';
			case 'gallery':
				$n = count($b['images']);
				$h = '<div class="agx-c-gallery agx-c-gallery-' . min($n, 4) . '">';
				foreach ($b['images'] as $im) { $h .= self::image($im); }
				return $h . '</div>';
			case 'video':
				return '<div class="agx-films agx-c-films">' . self::video($b) . '</div>';
			case 'videos':
				$h = '<div class="agx-films agx-c-films">';
				foreach ($b['items'] as $v) { $h .= self::video($v); }
				return $h . '</div>';
			case 'btn':
				return '<div class="agx-c-btns">' . self::button($b, true) . '</div>';
			case 'btns':
				$h = '<div class="agx-c-btns">';
				foreach ($b['items'] as $i => $x) { $h .= self::button($x, $i === 0); }
				return $h . '</div>';
			case 'note':
				return '<div class="agx-c-note">' . self::blocks($b['blocks'], $ctx) . '</div>';
			case 'quote':
				return '<blockquote class="agx-c-quote">' . self::inline($b['html']) . '</blockquote>';
			case 'cards':
				return self::cards($b, $ctx);
		}
		return '';
	}

	private static function button($b, $primary) {
		$href = $b['href'];
		$ext = preg_match('~^https?://~i', $href);
		return '<a class="agx-button' . ($primary ? '' : ' agx-button-line') . '" href="' . self::e($href) . '"' . ($ext ? ' rel="noopener" target="_blank"' : '') . '>' . self::inline(strip_tags($b['html'], '<strong><em>')) . '</a>';
	}

	private static function cards($b, $ctx) {
		$items = $b['items'];
		$n = count($items);
		// Decide the visual variant: numbered steps, stat tiles, media cards, or text cards.
		$numbered = !empty($b['steps']);
		$media = 0; $short = 0;
		foreach ($items as $c) {
			if ($c['num'] !== '') { $numbered = true; }
			$has_img = false; $len = 0;
			foreach ($c['blocks'] as $x) {
				if ($x['t'] === 'img' || $x['t'] === 'gallery') { $has_img = true; }
				if (isset($x['html'])) { $len += mb_strlen(self::text($x['html'])); }
				if ($x['t'] === 'list') { $len += 200; }
			}
			if ($has_img) { $media++; }
			if ($len <= 60 && !$has_img) { $short++; }
		}
		$variant = $numbered ? 'steps' : ($media >= max(1, $n / 2) ? 'media' : ($short === $n && $n >= 2 ? 'stats' : 'text'));
		$cols = $variant === 'stats' ? min(4, $n) : ($n === 2 || $n === 4 ? 2 : ($n === 1 ? 1 : 3));
		$h = '<div class="agx-c-cards agx-c-cards-' . $variant . ' agx-c-cols-' . $cols . '">';
		foreach ($items as $i => $c) {
			$h .= '<article class="agx-c-card">';
			if ($variant === 'steps') { $h .= '<span class="agx-c-num">' . self::e($c['num'] !== '' ? $c['num'] : str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) . '</span>'; }
			$blocks = $c['blocks'];
			// Media cards: image first, then text.
			if ($variant === 'media') {
				$imgs = []; $rest = [];
				foreach ($blocks as $x) { if ($x['t'] === 'img') { $imgs[] = $x; } else { $rest[] = $x; } }
				foreach ($imgs as $im) { $h .= self::image($im, 'agx-c-card-img'); }
				$blocks = $rest;
			}
			// Short first paragraph followed by a title reads as a label above it (stat tiles).
			$h .= '<div class="agx-c-card-body">' . self::blocks($blocks, $ctx) . '</div></article>';
		}
		return $h . '</div>';
	}

	public static function blocks($blocks, $ctx = []) {
		$h = '';
		foreach ($blocks as $b) { $h .= self::block($b, $ctx); }
		return $h;
	}

	/* ------------------------------------------------------------ sections */

	/**
	 * Renders sections. Each section: optional header (eyebrow, first h2, following lead/p)
	 * then body. A section whose only media is a single image uses a split layout.
	 */
	public static function sections($sections, $opts = []) {
		$out = '';
		$i = 0;
		$tones = ['dark', 'light'];
		foreach ($sections as $s) {
			$blocks = $s['blocks'];
			if (!$blocks) { continue; }
			$tone = ($s['tone'] ?? '') === 'dark' ? 'dark' : $tones[$i % 2];
			$i++;
			// Header: leading eyebrow + h2 (+ up to one lead/p directly after).
			$head = [];
			$k = 0;
			if (isset($blocks[$k]) && $blocks[$k]['t'] === 'eyebrow') { $head[] = $blocks[$k++]; }
			if (isset($blocks[$k]) && $blocks[$k]['t'] === 'h' && $blocks[$k]['l'] <= 2) {
				$head[] = $blocks[$k++];
				if (isset($blocks[$k]) && in_array($blocks[$k]['t'], ['lead'], true)) { $head[] = $blocks[$k++]; }
			} elseif ($head) {
				// eyebrow without heading stays in body
				$k = 0; $head = [];
			}
			$body = array_slice($blocks, $k);
			$single = [];
			$others = 0;
			foreach ($body as $b) {
				if ($b['t'] === 'img') { $single[] = $b; }
				elseif (in_array($b['t'], ['gallery','videos','video','cards','table'], true)) { $others++; }
			}
			$split = count($single) === 1 && $others === 0 && count($body) >= 2;
			$out .= '<section class="agx-c-section agx-c-' . $tone . '"><div class="agx-c-shell">';
			if ($split) {
				$img = $single[0];
				$rest = array_values(array_filter($body, function ($b) use ($img) { return $b !== $img; }));
				$out .= '<div class="agx-c-split"><div class="agx-c-copy">' . self::blocks($head) . self::blocks($rest) . '</div><div class="agx-c-media">' . self::image($img, 'agx-c-media-fig') . '</div></div>';
			} else {
				if ($head) { $out .= '<header class="agx-c-head">' . self::blocks($head) . '</header>'; }
				$out .= '<div class="agx-c-body">' . self::blocks($body) . '</div>';
			}
			$out .= '</div></section>';
		}
		return $out;
	}
}

// Shop sidebar (Elementor HTML widget with .gg-cat-list): hide links to categories that currently have no visible products.
final class AGST_ShopNav {
 static function boot(){add_filter('elementor/widget/render_content',[__CLASS__,'filter'],10,2);add_filter('elementor/frontend/the_content',function($c){return AGST_ShopNav::filter($c,null);},20);}
 static function count($slug){static $c=[];if(isset($c[$slug]))return $c[$slug];$t=get_term_by('slug',$slug,'product_cat');if(!$t)return $c[$slug]=-1;
  $q=new WP_Query(['post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>1,'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>(int)$t->term_id,'include_children'=>true],['taxonomy'=>'product_visibility','field'=>'name','terms'=>['exclude-from-catalog'],'operator'=>'NOT IN']]]);
  return $c[$slug]=(int)$q->found_posts;}
 static function filter($content,$widget){if(strpos((string)$content,'gg-cat-list')===false)return $content;
  return preg_replace_callback('~<li>\\s*<a href="[^"]*/shop/([a-z0-9-]+)/"[^>]*>.*?</a>\\s*</li>~is',function($m){return AGST_ShopNav::count($m[1])===0?'':$m[0];},$content);}
}
AGST_ShopNav::boot();

// Shop sidebar mirrors the price list: Gates, Fences, Patio Covers & Pergolas, Cladding with their subcategories.
final class AGST_ShopTree {
 const TOP=[88=>'Gates',127=>'Fences',398=>'Patio Covers & Pergolas',101=>'Cladding'];
 static function boot(){add_filter('elementor/frontend/the_content',[__CLASS__,'filter'],15);}
 static function filter($c){$s=strpos((string)$c,'<ul id="gg-categories"');if($s===false)return $c;
  $i=$s;$depth=0;$len=strlen($c);$end=false;
  while($i<$len){$o=strpos($c,'<ul',$i+1);$cl=strpos($c,'</ul>',$i+1);if($cl===false)break;if($i===$s){$depth=1;}
   if($o!==false&&$o<$cl){$depth++;$i=$o;continue;}$depth--;$i=$cl;if($depth===0){$end=$cl+5;break;}}
  if(!$end)return $c;$old=substr($c,$s,$end-$s);
  $icons=[];if(preg_match_all('~<li class="gg-cat-item" data-slug="([^"]+)">\s*<button[^>]*>(.*?)</button>~is',$old,$m,PREG_SET_ORDER)){foreach($m as $x){if(preg_match('~<img[^>]*>~is',$x[2],$im))$icons[$x[1]]=$im[0];}}
  $html='<ul id="gg-categories" class="gg-cat-list">';
  foreach(self::TOP as $id=>$label){$t=get_term($id,'product_cat');if(!$t||is_wp_error($t)||AGST_ShopNav::count($t->slug)<1)continue;
   $kids=get_terms(['taxonomy'=>'product_cat','parent'=>$id,'hide_empty'=>false,'orderby'=>'meta_value_num','meta_key'=>'order','order'=>'ASC']);
   $items='<li><a href="'.esc_url(get_term_link($t)).'">View All '.esc_html($label).'</a></li>';
   foreach($kids as $k){$n=(int)get_term_meta($k->term_id,'order',true);if($n<1||AGST_ShopNav::count($k->slug)<1)continue;$items.='<li><a href="'.esc_url(get_term_link($k)).'">'.esc_html(html_entity_decode($k->name)).'</a></li>';}
   $icon=$icons[$t->slug]??'';
   $html.='<li class="gg-cat-item" data-slug="'.esc_attr($t->slug).'"><button class="gg-cat-toggle" aria-expanded="false">'.$icon.' '.esc_html($label).' ▼</button><ul class="gg-subcats">'.$items.'</ul></li>';}
  $html.='</ul>';
  return substr($c,0,$s).$html.substr($c,$end);}
}
AGST_ShopTree::boot();


// ===== Catalog pages (/shop and product categories) organised like the price list =====
final class AGST_ShopFront {
 // H1 overrides for top categories (keeps the ranking keyword of the live page).
 const H1=['pergola'=>'Pergola & Patio Cover Kits'];
 // Price-list systems, in list order: category slug => [label, lead].
 const SYSTEMS=[
  'aluminum-gates'=>['Gates','Gate frame kits, slat packs, complete DIY and heavy-duty kits, posts and sliding systems.'],
  'aluminum-fence'=>['Fences','Complete fence kits, slats, posts, C-channels, spacers, base plates and rails.'],
  'pergola'=>['Patio Covers & Pergolas','Complete DIY pergola and patio cover kits: automatic louver, slat, beam and insulated roofs, freestanding or cantilever.'],
  'wall-cladding'=>['Cladding','T&G cladding panels, L-shapes and trims, and the Click System.'],
 ];
 static function boot(){
  add_filter('template_include',function($f){return self::active()?__DIR__.'/single-product.php':$f;},1000);
  add_filter('body_class',function($c){if(self::active()){$c[]='agst-full-layout';$c[]='agst-catalog';}return $c;});
  add_action('woocommerce_before_add_to_cart_form',function(){global $product;if(!$product)return;$s=self::soon($product->get_id());if($s==='')return;echo '<p class="agx-soon" style="margin:0 0 14px;padding:10px 14px;border:1px solid #f1c7a8;background:#fff3ea;color:#8a3b0a;border-radius:9px;font-size:14px"><strong>'.($s==='all'?'Coming soon':esc_html($s).' coming soon').'</strong> — please confirm availability with us before ordering.</p>';});
  add_action('wp_enqueue_scripts',function(){if(self::active()){wp_enqueue_style('agst-storefront',plugins_url('storefront.css',__FILE__),[],AGST_Catalog::ASSET_VERSION);}},99);
 }
 static function active(){
  if(is_admin()||!function_exists('is_shop'))return false;
  foreach(array_keys($_GET) as $k){if(preg_match('/^(filter_|query_type_|yith_wcan|min_price|max_price|rating_filter|orderby|paged|product-page)/',$k))return false;}
  if(is_shop()&&!is_search())return true;
  if(is_product_category())return true;
  if(is_search()&&get_query_var('post_type')==='product')return true;
  return false;
 }
 static function visible_ids($term_id,$children=true){
  $q=new WP_Query(['post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>-1,'orderby'=>['menu_order'=>'ASC','title'=>'ASC'],'no_found_rows'=>true,
   'tax_query'=>[['taxonomy'=>'product_cat','field'=>'term_id','terms'=>(int)$term_id,'include_children'=>$children],['taxonomy'=>'product_visibility','field'=>'name','terms'=>['exclude-from-catalog'],'operator'=>'NOT IN']]]);
  return $q->posts;
 }
 static function groups($term){
  $kids=get_terms(['taxonomy'=>'product_cat','parent'=>$term->term_id,'hide_empty'=>false,'orderby'=>'meta_value_num','meta_key'=>'order','order'=>'ASC']);
  $out=[];if(is_wp_error($kids))return $out;
  foreach($kids as $k){if((int)get_term_meta($k->term_id,'order',true)<1)continue;$ids=self::visible_ids($k->term_id);if($ids)$out[]=['term'=>$k,'ids'=>$ids];}
  return $out;
 }
 static function system_term($term){
  while($term&&!isset(self::SYSTEMS[$term->slug])&&$term->parent){$term=get_term($term->parent,'product_cat');}
  return ($term&&isset(self::SYSTEMS[$term->slug]))?$term:null;
 }
 static function label($term){return isset(self::SYSTEMS[$term->slug])?self::SYSTEMS[$term->slug][0]:html_entity_decode($term->name,ENT_QUOTES,'UTF-8');}
 static function thumb($term){
  $aid=(int)get_term_meta($term->term_id,'thumbnail_id',true);if($aid){$u=wp_get_attachment_image_url($aid,'woocommerce_thumbnail');if($u)return $u;}
  foreach(self::visible_ids($term->term_id) as $id){$p=wc_get_product($id);if($p&&$p->get_image_id()){return wp_get_attachment_image_url($p->get_image_id(),'woocommerce_thumbnail');}}
  return wc_placeholder_img_src('woocommerce_thumbnail');
 }
 static function colors($p){
  if(!$p->is_type('variable'))return [];
  foreach($p->get_attributes() as $k=>$a){$n=strtolower(wc_attribute_label($a->get_name()));if(strpos($n,'color')===false&&strpos($n,'colour')===false&&strpos($n,'finish')===false)continue;
   return $a->is_taxonomy()?array_map(function($t){return $t->name;},wc_get_product_terms($p->get_id(),$a->get_name(),['fields'=>'all'])):$a->get_options();}
  return [];
 }
 static function swatch($name){
  $map=['black'=>'#202222','bronze'=>'#79634b','white'=>'#f3f0e9','sand'=>'#c6b69a','clay'=>'#a8705a','gray'=>'#8c9091','grey'=>'#8c9091','walnut'=>'#5b3d2b','ipe'=>'#6b4a33','teak'=>'#8a5a36','oak'=>'#b88a55','custom'=>'conic-gradient(#e74,#fc3,#4b8,#39f,#e74)'];
  $n=strtolower($name);foreach($map as $k=>$v){if(strpos($n,$k)!==false)return $v;}return '#b9bcbb';
 }
 static function soon($id){$v=(string)get_post_meta($id,'_agst_coming_soon',true);return $v===''?'':$v;}
 static function card($id){
  $p=wc_get_product($id);if(!$p)return '';
  $url=get_permalink($id);$name=$p->get_name();
  $img=$p->get_image_id()?wp_get_attachment_image($p->get_image_id(),'woocommerce_thumbnail',false,['loading'=>'lazy','decoding'=>'async','alt'=>esc_attr($name)]):wc_placeholder_img('woocommerce_thumbnail');
  $price=$p->get_price_html();$purch=$p->is_purchasable()&&$p->get_price()!=='';
  if(!$purch||trim(wp_strip_all_tags($price))===''||stripos(wp_strip_all_tags($price),'call for price')!==false)$price='<span class="agc-ask">Price on request</span>';
  $cols=self::colors($p);$sw='<div class="agc-swatches">';
  if($cols){$sw='<div class="agc-swatches" aria-label="Available colours: '.esc_attr(implode(', ',$cols)).'">';foreach(array_slice($cols,0,6) as $c){$sw.='<span title="'.esc_attr($c).'" style="background:'.esc_attr(self::swatch($c)).'"></span>';}$sw.='<em>'.count($cols).' '.(count($cols)===1?'colour':'colours').'</em>';}
  $sw.='</div>';
  $s=self::soon($id);if($s===''&&stripos($name,'coming soon')!==false)$s='all';
  $soon=$s===''?'':'<span class="agc-badge agc-badge-soon">'.($s==='all'?'Coming soon':esc_html($s).' coming soon').'</span>';
  $cta=$purch?($p->is_type('variable')?'Choose options':'View product'):'Request a quote';
  return '<article class="agc-card"><a class="agc-card-media" href="'.esc_url($url).'" tabindex="-1" aria-hidden="true">'.$img.$soon.'</a><div class="agc-card-body"><h3 class="agc-card-title" title="'.esc_attr($name).'"><a href="'.esc_url($url).'">'.esc_html($name).'</a></h3>'.$sw.'<div class="agc-card-foot"><div class="agc-price">'.$price.'</div><a class="agc-card-btn" href="'.esc_url($url).'">'.$cta.'<span aria-hidden="true">→</span></a></div></div></article>';
 }
 static function pthumb($ids){foreach($ids as $id){$t=get_post_thumbnail_id($id);if($t){$u=wp_get_attachment_image_url($t,'woocommerce_thumbnail');if($u)return $u;}}return wc_placeholder_img_src('woocommerce_thumbnail');}
 static function grid($ids){$h='<div class="agc-grid">';foreach($ids as $id)$h.=self::card($id);return $h.'</div>';}
 static function crumbs($items){$h='<nav class="agc-crumbs" aria-label="Breadcrumb"><a href="'.esc_url(home_url('/')).'">Home</a>';foreach($items as $i){$h.='<span aria-hidden="true">/</span>'.($i[1]?'<a href="'.esc_url($i[1]).'">'.esc_html($i[0]).'</a>':'<span aria-current="page">'.esc_html($i[0]).'</span>');}return $h.'</nav>';}
 static function search_form(){return '<form class="agc-search" role="search" method="get" action="'.esc_url(home_url('/')).'"><label class="screen-reader-text" for="agc-s">Search products</label><input id="agc-s" type="search" name="s" value="'.esc_attr(get_search_query()).'" placeholder="Search systems, sizes, colours…"><input type="hidden" name="post_type" value="product"><button type="submit">Search</button></form>';}
 static function systems_nav($current=''){
  $h='<nav class="agc-systems" aria-label="Product systems">';
  foreach(self::SYSTEMS as $slug=>$s){$t=get_term_by('slug',$slug,'product_cat');if(!$t)continue;$h.='<a href="'.esc_url(get_term_link($t)).'"'.($slug===$current?' aria-current="page"':'').'>'.esc_html($s[0]).'</a>';}
  return $h.'</nav>';
 }
 static function help(){
  return '<section class="agc-help"><div><p class="agc-eyebrow">Need a hand?</p><h2>Not sure which parts you need?</h2><p>Send us your opening sizes and we will put the complete material list together for you.</p></div><div class="agc-help-actions"><a class="agc-btn agc-btn-accent" href="'.esc_url(home_url('/online-quote/')).'">Get an online quote</a><a class="agc-btn agc-btn-line" href="'.esc_url(home_url('/contacts/')).'">Talk to a specialist</a></div></section>';
 }
 static function about($term){
  $d=trim((string)term_description($term->term_id,'product_cat'));if(strlen(wp_strip_all_tags($d))<40)return '';
  return '<section class="agc-about"><div class="agc-shell"><details><summary>About '.esc_html(self::label($term)).'</summary><div class="agc-about-body">'.wp_kses_post($d).'</div></details></div></section>';
 }
 static function render(){
  echo '<main id="agc" class="agx agc">';
  if(function_exists('wc_print_notices'))wc_print_notices();
  if(is_search())self::render_search();
  elseif(is_product_category())self::render_category(get_queried_object());
  else self::render_shop();
  echo self::help().'</main>';
 }
 static function render_shop(){
  $sys=[];$total=0;
  foreach(self::SYSTEMS as $slug=>$s){$t=get_term_by('slug',$slug,'product_cat');if(!$t)continue;$ids=self::visible_ids($t->term_id);if(!$ids)continue;$sys[]=['slug'=>$slug,'s'=>$s,'t'=>$t,'ids'=>$ids,'groups'=>self::groups($t)];$all=array_merge($all??[],$ids);}$total=count(array_unique($all));
  $jump='';foreach($sys as $x){$jump.='<a class="agc-jump" href="#sys-'.esc_attr($x['slug']).'"><span class="agc-jump-img"><img src="'.esc_url(self::thumb($x['t'])).'" alt="" loading="lazy"></span><span class="agc-jump-text"><strong>'.esc_html($x['s'][0]).'</strong><em>'.count($x['ids']).' products</em></span></a>';}
  echo '<section class="agc-hero"><div class="agc-shell">'.self::crumbs([['Shop','']]).'<p class="agc-eyebrow">Aluglobus shop · '.$total.' products</p><h1>Everything on our current price list.</h1><p class="agc-lead">Choose your system, then the part you need. Prices shown are base prices — bundle and contractor discounts are available.</p>'.self::search_form().'<div class="agc-jumps">'.$jump.'</div></div></section>';
  echo '<section class="agc-body"><div class="agc-shell">';
  $n=0;foreach($sys as $x){$n++;$tiles='';$i=0;
   foreach($x['groups'] as $g){$i++;$gt=$g['term'];$tiles.='<a class="agc-tile" href="'.esc_url(get_term_link($gt)).'"><span class="agc-tile-img"><img src="'.esc_url(self::pthumb($g['ids'])).'" alt="" loading="lazy"></span><span class="agc-tile-body"><span class="agc-num">'.$i.'</span><strong>'.esc_html(html_entity_decode($gt->name,ENT_QUOTES,'UTF-8')).'</strong><em>'.count($g['ids']).' '.(count($g['ids'])===1?'product':'products').'</em></span></a>';}
   $nog=!$x['groups'];echo '<section class="agc-group agc-sys" id="sys-'.esc_attr($x['slug']).'"><header class="agc-group-head"><div><span class="agc-group-num">'.sprintf('%02d',$n).' · '.($nog?count($x['ids']).' products':count($x['groups']).' groups').'</span><h2>'.esc_html($x['s'][0]).'</h2><p>'.esc_html($x['s'][1]).'</p></div><a class="agc-group-link agc-sys-all" href="'.esc_url(get_term_link($x['t'])).'">Shop all '.count($x['ids']).' '.esc_html(strtolower($x['s'][0])).' <span aria-hidden="true">→</span></a></header>'.($nog?self::grid($x['ids']):'<div class="agc-tiles">'.$tiles.'</div>').'</section>';}
  echo '</div></section>';
 }
 static function render_category($term){
  $sys=self::system_term($term);$is_top=$sys&&$sys->term_id===$term->term_id;
  $crumb=[['Shop',get_permalink(wc_get_page_id('shop'))]];
  if($sys&&!$is_top)$crumb[]=[self::label($sys),get_term_link($sys)];
  $crumb[]=[self::label($term),''];
  $lead=$is_top?self::SYSTEMS[$term->slug][1]:trim(wp_strip_all_tags((string)term_description($term->term_id,'product_cat')));
  if(!$is_top&&(strlen($lead)>180||$lead===''))$lead=$sys?'Part of our '.self::label($sys).' range.':'';
  $groups=$is_top?self::groups($term):[];
  $count=$is_top?count(self::visible_ids($term->term_id)):0;
  echo '<section class="agc-hero agc-hero-compact"><div class="agc-shell">'.self::crumbs($crumb).self::systems_nav($sys?$sys->slug:'').'<p class="agc-eyebrow">'.($is_top?($groups?'Price list · '.count($groups).' groups':count(self::visible_ids($term->term_id)).' products'):($sys?esc_html(self::label($sys)):'Shop')).'</p><h1>'.esc_html(($is_top&&isset(self::H1[$term->slug]))?self::H1[$term->slug]:self::label($term)).'</h1>'.($lead?'<p class="agc-lead">'.esc_html($lead).'</p>':'').'</div></section>';
  if($is_top){
   $chips='';$i=0;foreach($groups as $g){$i++;$chips.='<a href="#'.esc_attr($g['term']->slug).'"><span class="agc-num">'.$i.'</span>'.esc_html(html_entity_decode($g['term']->name,ENT_QUOTES,'UTF-8')).'<em>'.count($g['ids']).'</em></a>';}
   echo '<nav class="agc-chips" aria-label="'.esc_attr(self::label($term)).' groups"><div class="agc-shell"><div class="agc-chips-row">'.$chips.'</div></div></nav><section class="agc-body"><div class="agc-shell">';
   $i=0;foreach($groups as $g){$i++;$t=$g['term'];$desc=trim(wp_strip_all_tags((string)term_description($t->term_id,'product_cat')));
    echo '<section class="agc-group" id="'.esc_attr($t->slug).'"><header class="agc-group-head"><div><span class="agc-group-num">'.sprintf('%02d',$i).'</span><h2>'.esc_html(html_entity_decode($t->name,ENT_QUOTES,'UTF-8')).'</h2>'.($desc&&strlen($desc)<160?'<p>'.esc_html($desc).'</p>':'').'</div><a class="agc-group-link" href="'.esc_url(get_term_link($t)).'">'.count($g['ids']).' products <span aria-hidden="true">→</span></a></header>'.self::grid($g['ids']).'</section>';}
   if(!$groups)echo self::grid(self::visible_ids($term->term_id));
   echo '</div></section>';
  }else{
   if($sys){$sib=self::groups($sys);$chips='';$i=0;foreach($sib as $g){$i++;$cur=$g['term']->term_id===$term->term_id;$chips.='<a href="'.esc_url(get_term_link($g['term'])).'"'.($cur?' aria-current="page"':'').'><span class="agc-num">'.$i.'</span>'.esc_html(html_entity_decode($g['term']->name,ENT_QUOTES,'UTF-8')).'<em>'.count($g['ids']).'</em></a>';}
    echo '<nav class="agc-chips" aria-label="'.esc_attr(self::label($sys)).' groups"><div class="agc-shell"><div class="agc-chips-row">'.$chips.'</div></div></nav>';}
   $ids=self::visible_ids($term->term_id);
   echo '<section class="agc-body"><div class="agc-shell">'.($ids?self::grid($ids):'<p class="agc-empty">There are no products in this group right now. <a href="'.esc_url(get_permalink(wc_get_page_id('shop'))).'">Back to the shop</a></p>').'</div></section>';
  }
  echo self::about($term);
 }
 static function render_search(){
  global $wp_query;$ids=wp_list_pluck($wp_query->posts,'ID');
  echo '<section class="agc-hero agc-hero-compact"><div class="agc-shell">'.self::crumbs([['Shop',get_permalink(wc_get_page_id('shop'))],['Search','']]).self::systems_nav().'<p class="agc-eyebrow">'.count($ids).' results</p><h1>Results for “'.esc_html(get_search_query()).'”</h1>'.self::search_form().'</div></section>';
  echo '<section class="agc-body"><div class="agc-shell">'.($ids?self::grid($ids):'<p class="agc-empty">No products match your search. Try a size (6x6), a profile (ALU 40) or a colour.</p>').'</div></section>';
 }
}
AGST_ShopFront::boot();

// Admin-only helper: import a price-list image from the live quote builder into the media library.
add_action('wp_ajax_agst_sideload',function(){check_ajax_referer('agst-catalog','nonce');
 if(!current_user_can('upload_files'))wp_send_json_error('No permission',403);
 $url=esc_url_raw(wp_unslash($_POST['url']??''));
 if(!preg_match('~^https://aluglobusfence\.com/wp-content/plugins/quote-builder-alu2026/assets/images/AG-\d{4}\.(?:jpe?g|png|webp)$~',$url))wp_send_json_error('URL not allowed',400);
 $hash=md5($url);$prior=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'fields'=>'ids','meta_key'=>'_agst_source_url','meta_value'=>$url]);if($prior)wp_send_json_success(['id'=>(int)$prior[0],'reused'=>true]);
 require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
 $id=media_sideload_image($url,0,sanitize_text_field(wp_unslash($_POST['alt']??'')),'id');if(is_wp_error($id))wp_send_json_error($id->get_error_message(),500);
 update_post_meta($id,'_agst_source_url',$url);if(!empty($_POST['alt']))update_post_meta($id,'_wp_attachment_image_alt',sanitize_text_field(wp_unslash($_POST['alt'])));
 wp_send_json_success(['id'=>(int)$id]);});

// Retired products: any old category path to a drafted product follows that product's cleanup redirect.
add_action('template_redirect',function(){
 if(!is_404())return;
 $path=(string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
 if(strpos($path,'/shop/')!==0)return;
 $slug=sanitize_title(basename(untrailingslashit($path)));if($slug===''||$slug==='shop')return;
 global $wpdb;
 $pid=(int)$wpdb->get_var($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_agst_cleanup' WHERE p.post_type='product' AND p.post_status IN ('draft','private','pending') AND p.post_name=%s LIMIT 1",$slug));
 if(!$pid)return;
 $table=$wpdb->prefix.'redirection_items';$to='';
 if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table){
  $to=(string)$wpdb->get_var($wpdb->prepare("SELECT action_data FROM {$table} WHERE group_id=5 AND status='enabled' AND action_type='url' AND url LIKE %s ORDER BY id DESC LIMIT 1",'%/'.$wpdb->esc_like($slug).'/'));
 }
 if($to===''){$c=json_decode((string)get_post_meta($pid,'_agst_cleanup',true),true);if(is_array($c)&&!empty($c['redirect']))$to=$c['redirect'];}
 if($to==='')return;
 wp_safe_redirect(home_url($to[0]==='/'?$to:'/'.$to),301,'AGST');exit;
},1);

// AGST round 2: brand is "Aluglobus Aluminum Systems" in SEO titles/descriptions (domain URLs untouched).
if(!function_exists('agst_brand_fix')){
 function agst_brand_fix($s){
  if(!is_string($s)||$s==='')return $s;
  $s=preg_replace('/(?<![\/\.\w@-])(?<!formerly )(?:ALU|Alu|alu)\s*(?:Globus|globus)\s*(?:FENCE|Fence)(?:\s*(?:&amp;|&)\s*Gate Systems)?(?!\w|\.com)/','Aluglobus Aluminum Systems',$s);
  // Old title suffix "… - Aluglobusfence.com" (domain used as a brand) -> brand name; domains in URLs/sentences stay untouched.
  $s=preg_replace('/\s*([-|–—])\s*Aluglobus\s*fence\.com\s*$/i',' $1 Aluglobus Aluminum Systems',$s);
  return str_replace('Aluglobus Aluminum Systems 2025','Aluglobus Aluminum Systems',$s);
 }
 foreach(['wpseo_title','wpseo_metadesc','wpseo_opengraph_title','wpseo_opengraph_desc','wpseo_opengraph_site_name','wpseo_twitter_title','wpseo_twitter_description'] as $agst_h){add_filter($agst_h,'agst_brand_fix',99);}
}

// ===== Elementor-editable product body (round 2) =====
// Every product's body content is stored as clean, standard Elementor widgets (heading, text editor,
// image, video, button, toggle) inside containers carrying agx classes, so it renders in the shared
// product design AND can be edited at any time with "Edit with Elementor". The original Elementor
// data is kept in _agst_el_original and can be restored per product.
final class AGST_ElBuild {
 const V='1.0';
 static function boot(){
  add_action('wp_ajax_agst_el_build',[__CLASS__,'ajax_build']);
  add_action('wp_ajax_agst_el_restore',[__CLASS__,'ajax_restore']);
  // QA: ?agst_qa=N (admins) hides the page chrome and body sections before N so a screenshot starts at section N.
  add_action('wp_head',function(){if(!isset($_GET['agst_qa'])||!current_user_can('manage_woocommerce'))return;$n=(int)$_GET['agst_qa'];echo '<style>#wpadminbar,header,.tbay-header,#tbay-header,.agx-breadcrumb,#agx-configure,.agx-trust,.agx-nav,.agx-notices{display:none!important}html{margin-top:0!important}</style><script>document.addEventListener("DOMContentLoaded",function(){var s=document.querySelectorAll(".agx-s,.agx-el-section,.agx-c-section");for(var i=0;i<'.$n.'&&i<s.length;i++)s[i].style.display="none";});</script>';},1);
  // QA: ?agst_legacy=1 (admins) renders the pre-Elementor body from the backed-up original data.
  if(isset($_GET['agst_legacy']))add_filter('get_post_metadata',function($v,$oid,$key,$single){if($key!=='_elementor_data'||!current_user_can('manage_woocommerce'))return $v;$o=get_post_meta($oid,'_agst_el_original',true);if(!is_array($o)||!isset($o['_elementor_data']))return $v;return $single?$o['_elementor_data']:[$o['_elementor_data']];},10,4);
  // Raw _elementor_data writes (REST/import) must not leave Elementor's rendered element cache stale.
  add_action('updated_post_meta',function($mid,$pid,$key){if($key==='_elementor_data')delete_post_meta($pid,'_elementor_element_cache');},10,3);
  add_action('wp_enqueue_scripts',function(){
   if(!class_exists('AGST_Storefront')||!AGST_Storefront::active()||!class_exists('\Elementor\Plugin'))return;
   $id=get_queried_object_id();if(!$id||!get_post_meta($id,'_agst_el',true))return;
   \Elementor\Plugin::$instance->frontend->enqueue_styles();
   if(class_exists('\Elementor\Core\Files\CSS\Post')){$css=\Elementor\Core\Files\CSS\Post::create($id);$css->enqueue();}
  },100);
 }
 static function preview($id){
  return class_exists('\Elementor\Plugin')&&isset(\Elementor\Plugin::$instance->preview)&&\Elementor\Plugin::$instance->preview->is_preview_mode($id);
 }
 /** HTML for the body area, or null when the product still uses the converted legacy body. */
 static function show($id){
  if(isset($_GET['agst_legacy'])&&current_user_can('manage_woocommerce'))return null;
  if(self::preview($id)){ob_start();the_content();return ob_get_clean();}
  if(!get_post_meta($id,'_agst_el',true)||!class_exists('\Elementor\Plugin'))return null;
  $h=\Elementor\Plugin::$instance->frontend->get_builder_content($id);
  return is_string($h)?$h:'';
 }
 /* ------------------------------------------------ builders */
 static function uid(){return substr(md5(uniqid('',true).mt_rand()),0,7);}
 static function w($type,$s,$cls=''){if($cls!=='')$s['_css_classes']=$cls;return ['id'=>self::uid(),'elType'=>'widget','widgetType'=>$type,'settings'=>$s,'elements'=>[],'isInner'=>false];}
 static function c($els,$cls,$dir='column',$inner=true){return ['id'=>self::uid(),'elType'=>'container','settings'=>['content_width'=>'full','flex_direction'=>$dir,'css_classes'=>$cls],'elements'=>array_values($els),'isInner'=>$inner];}
 static function in($h){return AGST_Blocks::inline((string)$h);}
 static function txt($h){return trim(html_entity_decode(strip_tags(str_replace('<br>',' ',(string)$h)),ENT_QUOTES|ENT_HTML5,'UTF-8'));}
 static function textish($t){return in_array($t,['p','lead','list','specs','table','title','quote','note'],true);}
 static function html($b){
  switch($b['t']){
   case 'p':return '<p>'.self::in($b['html']).'</p>';
   case 'lead':return '<p class="agx-c-lead">'.self::in($b['html']).'</p>';
   case 'title':return '<h4>'.self::in($b['html']).'</h4>';
   case 'quote':return '<blockquote>'.self::in($b['html']).'</blockquote>';
   case 'list':$t=!empty($b['ordered'])?'ol':'ul';$h='<'.$t.'>';foreach($b['items'] as $it)$h.='<li>'.self::in($it).'</li>';return $h.'</'.$t.'>';
   case 'specs':$h='<table class="agx-el-specs"><tbody>';foreach($b['rows'] as $r){$l=trim((string)$r[0]);$v=trim((string)$r[1]);if($l===''&&$v==='')continue;$h.=$l===''?'<tr><td colspan="2">'.self::in($v).'</td></tr>':'<tr><th>'.self::in(rtrim($l,':')).'</th><td>'.self::in($v).'</td></tr>';}return $h.'</tbody></table>';
   case 'table':$h='<table>';if(!empty($b['head'])){$h.='<thead><tr>';foreach($b['head'] as $c)$h.='<th>'.self::in($c).'</th>';$h.='</tr></thead>';}$h.='<tbody>';foreach($b['rows'] as $r){$h.='<tr>';foreach($r as $c)$h.='<td>'.self::in($c).'</td>';$h.='</tr>';}return $h.'</tbody></table>';
   case 'note':$h='';foreach($b['blocks'] as $x){if(self::textish($x['t']))$h.=self::html($x);elseif(isset($x['html']))$h.='<p>'.self::in($x['html']).'</p>';}return '<blockquote class="agx-el-note">'.$h.'</blockquote>';
  }
  return '';
 }
 static function att($im){
  foreach([$im['full']??'',$im['src']??''] as $u){if(!$u)continue;$id=attachment_url_to_postid($u);if(!$id)$id=attachment_url_to_postid(preg_replace('~-\d+x\d+(?=\.\w+$)~','',$u));if(!$id)$id=attachment_url_to_postid(preg_replace('~-scaled(?=\.\w+$)~','',$u));if($id)return $id;}
  return 0;
 }
 static function image($im,$cls='agx-el-img'){
  $id=self::att($im);$url=$id?wp_get_attachment_url($id):($im['full']??$im['src']);
  $s=['image'=>['url'=>$url,'id'=>$id?:'','alt'=>(string)($im['alt']??''),'source'=>'library'],'image_size'=>'large','link_to'=>'file','open_lightbox'=>'yes'];
  $cap=trim((string)($im['caption']??''));if($cap!==''){$s['caption_source']='custom';$s['caption']=self::txt($cap);}
  return self::w('image',$s,$cls);
 }
 static function video($v){
  if($v['kind']==='youtube')$s=['video_type'=>'youtube','youtube_url'=>'https://www.youtube.com/watch?v='.$v['id']];
  elseif($v['kind']==='vimeo')$s=['video_type'=>'vimeo','vimeo_url'=>'https://vimeo.com/'.$v['id']];
  else $s=['video_type'=>'hosted','hosted_url'=>['url'=>$v['src'],'id'=>'']];
  $s['rel']='';$s['modestbranding']='yes';
  return self::w('video',$s,'agx-el-video');
 }
 static function button($b,$primary){
  $href=(string)$b['href'];
  return self::w('button',['text'=>self::txt($b['html'])?:'Learn more','link'=>['url'=>$href,'is_external'=>preg_match('~^https?://~i',$href)?'on':'','nofollow'=>''],'size'=>'md'],'agx-el-btn'.($primary?'':' agx-el-btn-line'));
 }
 static function heading($html,$tag,$cls=''){return self::w('heading',['title'=>self::in($html),'header_size'=>$tag],$cls);}
 /** Blocks -> list of Elementor elements; consecutive text blocks share one text-editor widget. */
 static function els($blocks){
  $out=[];$buf='';
  $flush=function()use(&$out,&$buf){if(trim($buf)!==''){$out[]=self::w('text-editor',['editor'=>$buf],'agx-el-text');}$buf='';};
  foreach($blocks as $b){
   if(self::textish($b['t'])){$buf.=self::html($b);continue;}
   $flush();
   switch($b['t']){
    case 'eyebrow':$out[]=self::heading($b['html'],'p','agx-el-eyebrow');break;
    case 'h':$l=(int)$b['l'];$out[]=self::heading($b['html'],$l<=2?'h2':($l===3?'h3':'h4'),'agx-el-h'.min(4,max(2,$l)));break;
    case 'img':$out[]=self::image($b);break;
    case 'gallery':$n=count($b['images']);$imgs=[];foreach($b['images'] as $im)$imgs[]=self::image($im);$out[]=self::c($imgs,'agx-el-gallery agx-el-cols-'.min($n,4),'row');break;
    case 'video':$out[]=self::c([self::video($b)],'agx-el-videos','row');break;
    case 'videos':$vs=[];foreach($b['items'] as $v)$vs[]=self::video($v);$out[]=self::c($vs,'agx-el-videos','row');break;
    case 'btn':$out[]=self::c([self::button($b,true)],'agx-el-btns','row');break;
    case 'btns':$bs=[];foreach($b['items'] as $i=>$x)$bs[]=self::button($x,$i===0);$out[]=self::c($bs,'agx-el-btns','row');break;
    case 'faq':$tabs=[];foreach($b['items'] as $it){$a='';foreach($it['a'] as $x){if(self::textish($x['t']))$a.=self::html($x);elseif(isset($x['html']))$a.='<p>'.self::in($x['html']).'</p>';}$tabs[]=['_id'=>self::uid(),'tab_title'=>self::txt($it['q']),'tab_content'=>$a];}$out[]=self::w('toggle',['tabs'=>$tabs],'agx-el-faq');break;
    case 'cards':$out[]=self::cards($b);break;
   }
  }
  $flush();
  return $out;
 }
 static function cards($b){
  $items=$b['items'];$n=count($items);$numbered=!empty($b['steps']);$media=0;$short=0;
  foreach($items as $c){if(($c['num']??'')!=='')$numbered=true;$hi=false;$len=0;foreach($c['blocks'] as $x){if($x['t']==='img'||$x['t']==='gallery')$hi=true;if(isset($x['html']))$len+=mb_strlen(self::txt($x['html']));if($x['t']==='list')$len+=200;}if($hi)$media++;if($len<=60&&!$hi)$short++;}
  $variant=$numbered?'steps':($media>=max(1,$n/2)?'media':($short===$n&&$n>=2?'stats':'text'));
  $cols=$variant==='stats'?min(4,$n):($n===2||$n===4?2:($n===1?1:3));
  $cards=[];
  foreach($items as $i=>$c){
   $blocks=$c['blocks'];$els=[];
   if($variant==='steps')$els[]=self::heading(($c['num']??'')!==''?$c['num']:str_pad((string)($i+1),2,'0',STR_PAD_LEFT),'span','agx-el-num');
   if($variant==='media'){$rest=[];foreach($blocks as $x){if($x['t']==='img')$els[]=self::image($x,'agx-el-img agx-el-card-img');else $rest[]=$x;}$blocks=$rest;}
   $els=array_merge($els,self::els($blocks));
   $cards[]=self::c($els,'agx-el-card');
  }
  return self::c($cards,'agx-el-cards agx-el-cards-'.$variant.' agx-el-cols-'.$cols,'row');
 }
 static function build($sections){
  $data=[];$i=0;$tones=['dark','light'];
  foreach($sections as $s){
   $blocks=$s['blocks'];if(!$blocks)continue;
   $tone=($s['tone']??'')==='dark'?'dark':$tones[$i%2];$i++;
   $head=[];$k=0;
   if(isset($blocks[$k])&&$blocks[$k]['t']==='eyebrow')$head[]=$blocks[$k++];
   if(isset($blocks[$k])&&$blocks[$k]['t']==='h'&&$blocks[$k]['l']<=2){$head[]=$blocks[$k++];if(isset($blocks[$k])&&$blocks[$k]['t']==='lead')$head[]=$blocks[$k++];}elseif($head){$k=0;$head=[];}
   $body=array_slice($blocks,$k);$single=[];$others=0;
   foreach($body as $b){if($b['t']==='img')$single[]=$b;elseif(in_array($b['t'],['gallery','videos','video','cards','table'],true))$others++;}
   $split=count($single)===1&&$others===0&&count($body)>=2;
   if($split){$img=$single[0];$rest=array_values(array_filter($body,function($b)use($img){return $b!==$img;}));
    $els=[self::c([self::c(array_merge(self::els($head),self::els($rest)),'agx-el-copy'),self::c([self::image($img,'agx-el-img agx-el-media-img')],'agx-el-media')],'agx-el-split','row')];
   }else{$els=array_merge(self::els($head),self::els($body));}
   $sec=self::c($els,'agx-c-section agx-c-'.$tone.' agx-el-section','column',false);
   $data[]=$sec;
  }
  return $data;
 }
 /* ------------------------------------------------ migration */
 static function guard(){if(!current_user_can('manage_woocommerce'))wp_send_json_error('forbidden',403);if(!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('bad nonce',403);if(class_exists('AGST_Catalog'))AGST_Catalog::guard();}
 static function migrate($id,$force=false){
  $p=wc_get_product($id);if(!$p)return 'missing';
  if(get_post_meta($id,'_agst_el',true)&&!$force)return 'skip';
  $orig=get_post_meta($id,'_agst_el_original',true);
  if($force&&is_array($orig)){foreach($orig as $k=>$v){if($v===''||$v===null)delete_post_meta($id,$k);else update_post_meta($id,$k,is_string($v)?wp_slash($v):$v);}}
  if(!is_array($orig)){$orig=[];foreach(['_elementor_data','_elementor_edit_mode','_elementor_template_type','_elementor_version','_elementor_page_settings'] as $k)$orig[$k]=get_post_meta($id,$k,true);add_post_meta($id,'_agst_el_original',$orig,true);}
  delete_transient('agst_cc_'.$id);
  $spec=json_decode((string)get_post_meta($id,'_agst_page_spec',true),true);
  if(!is_array($spec)||!$spec){$c=AGST_Content::get($p);$spec=AGST_Spec::from_sections($c['sections']);}
  if(class_exists('AGST_V2Build')&&AGST_V2Build::enabled($id))$spec=AGST_V2Build::transform($spec,$id);
  $data=AGST_Spec::build($spec);
  update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));
  update_post_meta($id,'_elementor_edit_mode','builder');
  update_post_meta($id,'_elementor_template_type','product-post');
  if(defined('ELEMENTOR_VERSION'))update_post_meta($id,'_elementor_version',ELEMENTOR_VERSION);
  update_post_meta($id,'_elementor_page_settings',[]);
  foreach(['_elementor_css','_elementor_element_cache','_elementor_page_assets','_elementor_controls_usage'] as $k)delete_post_meta($id,$k);
  update_post_meta($id,'_agst_el','2.0');
  delete_transient('agst_cc_'.$id);
  return 'ok:'.count($data);
 }
 static function restore($id){
  $o=get_post_meta($id,'_agst_el_original',true);if(!is_array($o))return 'no-backup';
  foreach($o as $k=>$v){if($v===''||$v===null)delete_post_meta($id,$k);else update_post_meta($id,$k,is_string($v)?wp_slash($v):$v);}
  foreach(['_elementor_css','_elementor_element_cache','_elementor_page_assets','_agst_el'] as $k)delete_post_meta($id,$k);
  delete_transient('agst_cc_'.$id);return 'restored';
 }
 static function ajax_build(){self::guard();$out=[];foreach(array_map('intval',explode(',',(string)($_POST['ids']??''))) as $id){if($id)$out[$id]=self::migrate($id,!empty($_POST['force']));}wp_send_json_success($out);}
 static function ajax_restore(){self::guard();$out=[];foreach(array_map('intval',explode(',',(string)($_POST['ids']??''))) as $id){if($id)$out[$id]=self::restore($id);}wp_send_json_success($out);}
}
AGST_ElBuild::boot();

// ===== Product body design system (round 4): page spec -> Elementor widgets =====
// A page spec is a list of sections. Section: layout stack|split|cta, eyebrow, title, text (html),
// media {src,alt} (split), reverse (split), parts[]. Parts: text, chips, checklist, buttons, stats,
// gallery, cards, packages, steps, specs, table, video, faq, note, shortcode.
// Stored per product in _agst_page_spec (JSON). Without a stored spec one is derived from the converted content.
final class AGST_Spec {
 static function uid(){return substr(md5(uniqid('',true).mt_rand()),0,7);}
 static function w($type,$s,$cls=''){if($cls!=='')$s['_css_classes']=$cls;return ['id'=>self::uid(),'elType'=>'widget','widgetType'=>$type,'settings'=>$s,'elements'=>[],'isInner'=>false];}
 static function c($els,$cls,$dir='column',$inner=true){return ['id'=>self::uid(),'elType'=>'container','settings'=>['content_width'=>'full','flex_direction'=>$dir,'css_classes'=>$cls],'elements'=>array_values(array_filter($els)),'isInner'=>$inner];}
 static function in($h){return class_exists('AGST_Blocks')?AGST_Blocks::inline((string)$h):wp_kses((string)$h,['strong'=>[],'em'=>[],'br'=>[],'a'=>['href'=>[]],'sup'=>[],'sub'=>[]]);}
 static function txt($h){return trim(html_entity_decode(wp_strip_all_tags(str_replace(['<br>','<br/>','<br />'],' ',(string)$h)),ENT_QUOTES|ENT_HTML5,'UTF-8'));}
 static function rich($h){
  $h=preg_replace('~\[(?!/?(?:b|i)\])[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]~i','',(string)$h); // no raw shortcodes in copy
  return wp_kses($h,['p'=>['class'=>[]],'h3'=>[],'h4'=>[],'ul'=>['class'=>[]],'ol'=>[],'li'=>[],'strong'=>[],'em'=>[],'br'=>[],'a'=>['href'=>[],'target'=>[],'rel'=>[]],'table'=>['class'=>[]],'thead'=>[],'tbody'=>[],'tr'=>[],'th'=>[],'td'=>['colspan'=>[]],'blockquote'=>['class'=>[]],'sup'=>[],'sub'=>[]]);
 }
 static function head($tag,$html,$cls){$t=self::in($html);if(trim(strip_tags($t))==='')return null;return self::w('heading',['title'=>$t,'header_size'=>$tag],$cls);}
 static function text($html,$cls='agx-text'){$h=self::rich($html);if(trim(wp_strip_all_tags($h))==='')return null;return self::w('text-editor',['editor'=>$h],$cls);}
 static function att($u){
  if(!$u)return 0;$u=preg_replace('~^https?://[^/]+~','',$u);$abs=home_url($u);
  foreach([$abs,preg_replace('~-\d+x\d+(?=\.\w+$)~','',$abs),preg_replace('~-scaled(?=\.\w+$)~','',$abs)] as $x){$id=attachment_url_to_postid($x);if($id)return $id;}
  return 0;
 }
 static function image($im,$cls='agx-img'){
  if(empty($im['src']))return null;$id=!empty($im['id'])?(int)$im['id']:self::att($im['src']);$url=$id?wp_get_attachment_url($id):$im['src'];if(!$url)return null;
  // theme "large" is only 350px: big placements use the full file, grids the 768px size
  $size=preg_match('~split-img|band-img~',$cls)?'full':(preg_match('~gallery-img|card-img~',$cls)?'medium_large':'large');
  $s=['image'=>['url'=>$url,'id'=>$id?:'','alt'=>(string)($im['alt']??''),'source'=>'library'],'image_size'=>$size,'link_to'=>'file','open_lightbox'=>'yes'];
  $cap=trim((string)($im['caption']??''));if($cap!==''){$s['caption_source']='custom';$s['caption']=self::txt($cap);}
  return self::w('image',$s,$cls);
 }
 static function button($b,$i=0){
  $href=trim((string)($b['href']??''));$label=self::txt($b['text']??'');if($label==='')return null;if($href==='')$href='#agx-configure';
  $secondary=($b['style']??'')==='secondary'||(($b['style']??'')===''&&$i>0);
  $s=['text'=>$label,'link'=>['url'=>$href,'is_external'=>preg_match('~^https?://~i',$href)&&strpos($href,home_url())!==0?'on':'','nofollow'=>''],'size'=>'md'];
  // "#elementor-action:...popup..." renders as an empty href in a button link; use Elementor Pro's popup dynamic tag (still editable in Elementor).
  if(preg_match('~^#elementor-action:action=popup:open&settings=([A-Za-z0-9+/=%]+)~',$href,$m)){$ps=json_decode(base64_decode(urldecode($m[1])),true);if(!empty($ps['id'])){$s['link']=['url'=>'','is_external'=>'','nofollow'=>''];$s['__dynamic__']=['link'=>'[elementor-tag id="'.self::uid().'" name="popup" settings="'.urlencode(wp_json_encode(['action'=>'open','popup'=>(string)(int)$ps['id']])).'"]'];}}
  return self::w('button',$s,'agx-btn'.($secondary?' agx-btn--secondary':''));
 }
 static function buttons($items){$bs=[];foreach((array)$items as $i=>$b){$x=self::button($b,$i);if($x)$bs[]=$x;}return $bs?self::c($bs,'agx-btns','row'):null;}
 static function ul($items,$cls){$h='';foreach((array)$items as $it){$t=self::in(is_array($it)?($it['text']??''):$it);if(trim(strip_tags($t))!=='')$h.='<li>'.$t.'</li>';}return $h===''?null:self::w('text-editor',['editor'=>'<ul class="'.$cls.'">'.$h.'</ul>'],'agx-text agx-'.$cls);}
 static function video($v){
  $u=(string)($v['url']??'');if($u==='')return null;
  if(preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|watch\?v=))([a-zA-Z0-9_-]{11})~',$u,$m))$s=['video_type'=>'youtube','youtube_url'=>'https://www.youtube.com/watch?v='.$m[1]];
  elseif(preg_match('~vimeo\.com/(?:video/)?(\d+)~',$u,$m))$s=['video_type'=>'vimeo','vimeo_url'=>'https://vimeo.com/'.$m[1]];
  else $s=['video_type'=>'hosted','hosted_url'=>['url'=>$u,'id'=>'']];
  $s['rel']='';$s['modestbranding']='yes';
  // YouTube without a photo cover: use its own thumbnail so the tile is never blank and the player loads on click
  if(empty($v['poster']['src'])&&$s['video_type']==='youtube')$v['poster']=['src'=>'https://i.ytimg.com/vi/'.$m[1].'/hqdefault.jpg','id'=>0];
  if(!empty($v['poster']['src'])){$s['show_image_overlay']='yes';$s['image_overlay']=['url'=>$v['poster']['src'],'id'=>(int)($v['poster']['id']??0)];$s['image_overlay_size']='full';$s['lightbox']='';}return self::w('video',$s,'agx-video-w');
 }
 static function table($head,$rows,$cls=''){
  $h='<table'.($cls?' class="'.$cls.'"':'').'>';if($head){$h.='<thead><tr>';foreach($head as $c)$h.='<th>'.self::in($c).'</th>';$h.='</tr></thead>';}$h.='<tbody>';
  foreach((array)$rows as $r){$r=array_values((array)$r);$h.='<tr>';if($cls==='agx-spec'){$l=trim(self::txt($r[0]??''));$v=self::in($r[1]??'');$h.=$l===''?'<td colspan="2">'.$v.'</td>':'<th>'.self::in(rtrim($r[0],':')).'</th><td>'.$v.'</td>';}else{foreach($r as $c)$h.='<td>'.self::in($c).'</td>';}$h.='</tr>';}
  return self::w('text-editor',['editor'=>$h.'</tbody></table>'],'agx-text agx-table'.($cls?' '.$cls:''));
 }
 static function cols($n,$max=4){$n=(int)$n;return max(1,min($max,$n));}
 /** One part -> element(s). */
 static function part($p){
  $t=$p['type']??'';
  switch($t){
   case 'text':return [self::text($p['html']??'')];
   case 'chips':return [self::ul($p['items']??[],'agx-chips')];
   case 'checklist':return [self::ul($p['items']??[],'agx-check'.(count((array)($p['items']??[]))>5?' agx-check--2col':''))];
   case 'buttons':return [self::buttons($p['items']??[])];
   case 'note':return [self::text('<blockquote class="agx-note">'.self::rich($p['html']??'').'</blockquote>','agx-text agx-note-w')];
   // Shortcodes go in a text-editor widget: Elementor's shortcode widget dropped a closing </div> here and nested every later section.
   case 'drawings':$h='<div class="agx-draw-grid">';foreach((array)($p['images']??[]) as $x){if(empty($x['src']))continue;$h.='<a href="'.esc_url($x['src']).'"><img src="'.esc_url($x['src']).'" alt="'.esc_attr($x['alt']??'').'" loading="lazy"></a>';}$h.='</div>';return [self::w('toggle',['tabs'=>[['_id'=>self::uid(),'tab_title'=>'Show '.count((array)$p['images']).' product views and drawings','tab_content'=>$h]]],'agx-acc agx-drawings')];
   case 'shortcode':$code=trim(wp_strip_all_tags((string)($p['code']??'')));return $code===''?[]:[self::w('text-editor',['editor'=>'<div class="agx-ar">'.$code.'</div>'],'agx-text agx-shortcode')];
   case 'stats':$it=[];foreach((array)($p['items']??[]) as $x){$it[]=self::c([self::head('p',$x['label']??'','agx-stat-label'),self::head('p',$x['value']??'','agx-stat-value')],'agx-stat');}return $it?[self::c($it,'agx-grid agx-stats agx-cols-'.self::cols(count($it)),'row')]:[];
   case 'gallery':$im=[];foreach((array)($p['images']??[]) as $x){$e=self::image($x,'agx-img agx-gallery-img');if($e)$im[]=$e;}if(!$im)return [];$n=self::cols($p['cols']??count($im),4);if(count($im)===1)$n=1;return [self::c($im,'agx-grid agx-gallery agx-cols-'.$n,'row')];
   case 'video':$v=[];foreach((array)($p['videos']??[]) as $x){$e=self::video($x);if($e)$v[]=$e;}$n=count($v);return $v?[self::c($v,'agx-grid agx-videos agx-cols-'.($n<=3?$n:($n===4?2:3)),'row')]:[];
   case 'specs':return [self::table([],$p['rows']??[],'agx-spec')];
   case 'table':return [self::table($p['head']??[],$p['rows']??[])];
   case 'faq':$tabs=[];foreach((array)($p['items']??[]) as $qa){$q=is_array($qa)?($qa['q']??$qa[0]??''):'';$a=is_array($qa)?($qa['a']??$qa[1]??''):'';if(self::txt($q)==='')continue;$a=self::rich(preg_match('~^\s*<~',$a)?$a:'<p>'.esc_html($a).'</p>');$tabs[]=['_id'=>self::uid(),'tab_title'=>self::txt($q),'tab_content'=>$a];}return $tabs?[self::w('toggle',['tabs'=>$tabs],'agx-acc')]:[];
   case 'steps':$it=[];foreach(array_values((array)($p['items']??[])) as $i=>$x){$it[]=self::c([self::head('span',str_pad((string)($i+1),2,'0',STR_PAD_LEFT),'agx-num'),self::head('h3',$x['title']??'','agx-card-title'),self::text(self::para($x['text']??''))],'agx-card agx-step');}return $it?[self::c($it,'agx-grid agx-steps agx-cols-'.self::cols(count($it)===4?2:count($it),3),'row')]:[];
   case 'cards':$it=[];foreach((array)($p['items']??[]) as $x){$els=[];if(!empty($x['image']['src']))$els[]=self::image($x['image'],'agx-img agx-card-img');$els[]=self::head('h3',$x['title']??'','agx-card-title');$els[]=self::text(self::para($x['text']??''));if(!empty($x['list']))$els[]=self::ul($x['list'],'agx-bullets');if(!empty($x['button']))$els[]=self::buttons([$x['button']]);$it[]=self::c($els,'agx-card'.(!empty($x['image']['src'])?' agx-card--media':''));}$n=count($it);return $it?[self::c($it,'agx-grid agx-cards agx-cols-'.self::cols($p['cols']??($n===4||$n===2?2:min(3,$n)),4),'row')]:[];
   case 'packages':$it=[];$n=count((array)($p['items']??[]));foreach(array_values((array)($p['items']??[])) as $i=>$x){$els=[];if(!empty($x['image']['src']))$els[]=self::image($x['image'],'agx-img agx-card-img');$els[]=self::head('span',$x['badge']??'','agx-badge');$els[]=self::head('h3',$x['title']??'','agx-card-title');$els[]=self::head('p',$x['price']??'','agx-price-line');$els[]=self::head('p',$x['subtitle']??'','agx-subline');if(!empty($x['chips']))$els[]=self::ul($x['chips'],'agx-chips');$els[]=self::text(self::para($x['text']??''));if(!empty($x['list']))$els[]=self::ul($x['list'],'agx-check');if(!empty($x['button']))$els[]=self::buttons([array_merge(['style'=>($n===3&&$i===1)||$n===1?'primary':'secondary'],(array)$x['button'])]);$it[]=self::c($els,'agx-card agx-package'.(($n===3&&$i===1)?' agx-package--featured':''));}return $it?[self::c($it,'agx-grid agx-packages agx-cols-'.self::cols($n,3),'row')]:[];
  }
  return [];
 }
 static function para($t){$t=trim((string)$t);if($t==='')return '';return preg_match('~^\s*<(p|ul|ol|h3|h4|table)\b~i',$t)?$t:'<p>'.self::in($t).'</p>';}
 /** Full spec -> Elementor data. */
 static function build($spec){
  $data=[];$i=0;
  foreach((array)$spec as $s){
   $layout=$s['layout']??'stack';
   if($layout==='band'){$bm=(array)($s['media']??[]);unset($bm['caption']);$img=self::image($bm,'agx-img agx-band-img');if(!$img)continue;$cap=self::head('p',$s['title']??'','agx-band-cap');$data[]=self::c(array_filter([$img,$cap]),'agx-s agx-band','column',false);continue;}
   $tone=$layout==='cta'?'accent':(($s['tone']??'')?:($i%2===0?'dark':'light'));$i++;
   $head=array_values(array_filter([self::head('p',$s['eyebrow']??'','agx-kicker'),self::head('h2',$s['title']??'','agx-h2'),self::text(self::para($s['text']??''),'agx-text agx-intro')]));
   $parts=[];foreach((array)($s['parts']??[]) as $p)foreach(self::part($p) as $e)if($e)$parts[]=$e;
   if($layout==='split'&&!empty($s['media']['src'])){
    $copy=self::c(array_merge($head,$parts),'agx-split-copy');$media=self::c([self::image($s['media'],'agx-img agx-split-img')],'agx-split-media');
    $els=[self::c(!empty($s['reverse'])?[$media,$copy]:[$copy,$media],'agx-split'.(!empty($s['reverse'])?' agx-split--reverse':''),'row')];
   }elseif($layout==='cta'){$els=[self::c(array_merge($head,$parts),'agx-cta-inner')];}
   else{$els=[];if($head)$els[]=self::c($head,'agx-s-head');$els=array_merge($els,$parts);}
   if(!$els)continue;
   $data[]=self::c($els,'agx-s agx-s--'.($layout==='stack'?(($s['parts'][0]['type']??'text')):$layout).' agx-tone-'.$tone,'column',false);
  }
  return $data;
 }
 /* ------------------------------------------------ auto spec from converted sections */
 static function img_of($b){return ['src'=>$b['full']??$b['src']??'','alt'=>self::txt($b['alt']??''),'caption'=>self::txt($b['caption']??'')];}
 static function from_sections($sections){
  $spec=[];
  foreach((array)$sections as $s){
   $blocks=array_values($s['blocks']??[]);if(!$blocks)continue;
   $sec=['layout'=>'stack','eyebrow'=>'','title'=>'','text'=>'','parts'=>[]];$k=0;
   if(isset($blocks[$k])&&$blocks[$k]['t']==='eyebrow')$sec['eyebrow']=self::txt($blocks[$k++]['html']);
   if(isset($blocks[$k])&&$blocks[$k]['t']==='h'&&$blocks[$k]['l']<=2){$sec['title']=self::in($blocks[$k++]['html']);if(isset($blocks[$k])&&in_array($blocks[$k]['t'],['lead','p'],true)){$sec['text']='<p>'.self::in($blocks[$k++]['html']).'</p>';}}
   $body=array_slice($blocks,$k);
   $imgs=array_values(array_filter($body,function($b){return $b['t']==='img';}));$heavy=array_filter($body,function($b){return in_array($b['t'],['gallery','videos','video','cards','table','faq'],true);});
   if(count($imgs)===1&&!$heavy){$sec['layout']='split';$sec['media']=self::img_of($imgs[0]);$body=array_values(array_filter($body,function($b){return $b['t']!=='img';}));}
   $buf='';$flush=function()use(&$buf,&$sec){if(trim(wp_strip_all_tags($buf))!==''){$sec['parts'][]=['type'=>'text','html'=>$buf];}$buf='';};
   foreach($body as $b){
    switch($b['t']){
     case 'p':case 'lead':$buf.='<p>'.self::in($b['html']).'</p>';break;
     case 'title':$buf.='<h4>'.self::in($b['html']).'</h4>';break;
     case 'h':$buf.='<h3>'.self::in($b['html']).'</h3>';break;
     case 'eyebrow':$buf.='<p><strong>'.self::in($b['html']).'</strong></p>';break;
     case 'quote':$flush();$sec['parts'][]=['type'=>'note','html'=>'<p>'.self::in($b['html']).'</p>'];break;
     case 'list':$items=array_map(function($x){return self::in($x);},$b['items']);$short=count(array_filter($items,function($x){return mb_strlen(self::txt($x))<=28;}));
      if(!empty($b['ordered'])){$buf.='<ol>';foreach($items as $x)$buf.='<li>'.$x.'</li>';$buf.='</ol>';}
      elseif($short===count($items)&&count($items)>=2&&count($items)<=8){$flush();$sec['parts'][]=['type'=>'chips','items'=>$items];}
      else{$flush();$sec['parts'][]=['type'=>'checklist','items'=>$items];}break;
     case 'specs':$flush();$sec['parts'][]=['type'=>'specs','rows'=>$b['rows']];break;
     case 'table':$flush();$sec['parts'][]=['type'=>'table','head'=>$b['head']??[],'rows'=>$b['rows']];break;
     case 'note':$flush();$h='';foreach($b['blocks'] as $x){if(isset($x['html']))$h.='<p>'.self::in($x['html']).'</p>';}$sec['parts'][]=['type'=>'note','html'=>$h];break;
     case 'img':$flush();$sec['parts'][]=['type'=>'gallery','images'=>[self::img_of($b)],'cols'=>1];break;
     case 'gallery':$flush();$sec['parts'][]=['type'=>'gallery','images'=>array_map([__CLASS__,'img_of'],$b['images'])];break;
     case 'video':$flush();$sec['parts'][]=['type'=>'video','videos'=>[['url'=>self::vurl($b)]]];break;
     case 'videos':$flush();$sec['parts'][]=['type'=>'video','videos'=>array_map(function($v){return ['url'=>self::vurl($v)];},$b['items'])];break;
     case 'btn':$flush();$sec['parts'][]=['type'=>'buttons','items'=>[['text'=>$b['html'],'href'=>$b['href']]]];break;
     case 'btns':$flush();$sec['parts'][]=['type'=>'buttons','items'=>array_map(function($x){return ['text'=>$x['html'],'href'=>$x['href']];},$b['items'])];break;
     case 'faq':$flush();$sec['parts'][]=['type'=>'faq','items'=>array_map(function($it){$a='';foreach($it['a'] as $x){if(isset($x['html']))$a.='<p>'.self::in($x['html']).'</p>';elseif(($x['t']??'')==='list'){$a.='<ul>';foreach($x['items'] as $li)$a.='<li>'.self::in($li).'</li>';$a.='</ul>';}}return ['q'=>self::txt($it['q']),'a'=>$a];},$b['items'])];break;
     case 'cards':$flush();$sec['parts'][]=self::cards_part($b);break;
    }
   }
   $flush();
   if($sec['title']===''&&$sec['text']===''&&!$sec['parts'])continue;
   $spec[]=$sec;
  }
  return $spec;
 }
 static function vurl($v){if(($v['kind']??'')==='youtube')return 'https://www.youtube.com/watch?v='.$v['id'];if(($v['kind']??'')==='vimeo')return 'https://vimeo.com/'.$v['id'];return (string)($v['src']??'');}
 static function cards_part($b){
  $items=[];$steps=!empty($b['steps']);$allshort=true;
  foreach($b['items'] as $c){
   $it=['title'=>'','text'=>'','list'=>[]];$txt='';
   if(($c['num']??'')!=='')$steps=true;
   foreach($c['blocks'] as $x){
    if($x['t']==='img'&&empty($it['image']))$it['image']=self::img_of($x);
    elseif(in_array($x['t'],['title','h'],true)&&$it['title']==='')$it['title']=self::in($x['html']);
    elseif($x['t']==='list')$it['list']=array_merge($it['list'],array_map(function($l){return self::in($l);},$x['items']));
    elseif($x['t']==='btn')$it['button']=['text'=>$x['html'],'href'=>$x['href']];
    elseif(isset($x['html']))$txt.='<p>'.self::in($x['html']).'</p>';
   }
   $it['text']=$txt;if(mb_strlen(self::txt($txt))>40||$it['list']||!empty($it['image']))$allshort=false;
   $items[]=$it;
  }
  if($steps)return ['type'=>'steps','items'=>array_map(function($i){return ['title'=>$i['title'],'text'=>$i['text']];},$items)];
  if($allshort&&count($items)>=2&&count($items)<=4)return ['type'=>'stats','items'=>array_map(function($i){$v=self::txt($i['text']);return $v!==''?['label'=>self::txt($i['title']),'value'=>$v]:['label'=>'','value'=>self::txt($i['title'])];},$items)];
  return ['type'=>'cards','items'=>$items];
 }
}

// Staging spec workbench. Explicit submissions only; no catalog or URL changes.
final class AGST_SpecWorkbench {
 static function guard(){AGST_Catalog::guard();if(parse_url(home_url(),PHP_URL_HOST)!=='globusgates.online')wp_die('Staging only.');}
 static function page(){self::guard();echo '<div class="wrap"><h1>Product page specs</h1><p>Upload validated page specs to rebuild Elementor bodies. Prices, URLs and category assignments are preserved.</p>';
 if(!empty($_POST['agst_spec_submit'])){check_admin_referer('agst-spec-import');try{
 $raw=isset($_FILES['spec_file']['tmp_name'])&&is_uploaded_file($_FILES['spec_file']['tmp_name'])?file_get_contents($_FILES['spec_file']['tmp_name']):wp_unslash($_POST['spec_json']??'');$batch=json_decode($raw,true);if(isset($batch['id']))$batch=[$batch];if(!is_array($batch)||!$batch)throw new RuntimeException('Invalid JSON');
 foreach($batch as $doc){$id=(int)($doc['id']??0);$p=wc_get_product($id);if(!$p||empty($doc['spec'])||!is_array($doc['spec']))throw new RuntimeException('Invalid product/spec');foreach($doc['spec'] as $s){if(!in_array($s['layout']??'stack',['stack','split','cta'],true))throw new RuntimeException('Invalid layout');if(($s['layout']??'')==='split'&&empty($s['media']['src']))throw new RuntimeException('Split image missing');}}
 foreach($batch as $doc){$id=(int)$doc['id'];$p=wc_get_product($id);$identity=AGST_Preservation::identity($id);$backup=[];foreach(['_agst_page_spec','_elementor_data','_elementor_page_settings','_elementor_edit_mode','_agst_el'] as $k)$backup[$k]=get_post_meta($id,$k,true);add_post_meta($id,'_agst_spec_workbench_backup_20261001',$backup,true);update_post_meta($id,'_agst_page_spec',wp_slash(wp_json_encode($doc['spec'])));$result=AGST_ElBuild::migrate($id,true);AGST_Preservation::verify($identity);echo '<p class="notice notice-success">'.esc_html($id.' '.$p->get_name().' rebuilt '.$result.'; URL/category identity verified.').'</p>';}
 }catch(Throwable $e){echo '<p class="notice notice-error">'.esc_html($e->getMessage()).'</p>';}}
 echo '<form method="post" enctype="multipart/form-data">';wp_nonce_field('agst-spec-import');echo '<p><label>Spec JSON file <input type="file" name="spec_file" accept=".json"></label></p><p><label>Or spec JSON<textarea name="spec_json" style="display:block;width:100%;height:120px"></textarea></label></p><button class="button button-primary" name="agst_spec_submit" value="1">Upload specs and rebuild</button></form><p>AR shortcode available: '.(shortcode_exists('ar-display')?'yes':'no').'</p>';
 echo '<form method="post">';wp_nonce_field('agst-spec-import');echo '<button class="button" name="agst_spec_export" value="1">Export original product content</button></form>';
 if(!empty($_POST['agst_spec_export'])){check_admin_referer('agst-spec-import');$out=[];foreach(get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids']) as $id){$p=wc_get_product($id);$out[]=['id'=>$id,'name'=>$p->get_name(),'url'=>get_permalink($id),'short'=>$p->get_short_description(),'description'=>$p->get_description(),'original'=>get_post_meta($id,'_agst_el_original',true),'spec'=>get_post_meta($id,'_agst_page_spec',true),'seo_html'=>get_post_meta($id,'_agst_seo_html',true)];}echo '<textarea readonly aria-label="Original content JSON" style="width:100%;height:180px">'.esc_textarea(wp_json_encode($out)).'</textarea>';}
 echo '</div>'; }
}
add_action('admin_menu',function(){add_submenu_page('woocommerce','Product page specs','Product page specs','manage_woocommerce','agst-page-specs',['AGST_SpecWorkbench','page']);});

add_action('admin_menu',function(){add_submenu_page('woocommerce','Product visual QA','Product visual QA','manage_woocommerce','agst-spec-preview',function(){AGST_SpecWorkbench::guard();$id=(int)($_GET['product']??58015);$width=in_array((int)($_GET['width']??1366),[390,768,1366],true)?(int)($_GET['width']??1366):1366;$section=max(0,(int)($_GET['section']??0));echo '<div class="wrap"><h1>Product visual QA</h1><form><input type="hidden" name="page" value="agst-spec-preview"><label>Product ID <input name="product" value="'.$id.'"></label><label>Width <select name="width">';foreach([390,768,1366] as $w)echo '<option '.selected($w,$width,false).'>'.$w.'</option>';echo '</select></label><label>Section <input name="section" value="'.$section.'"></label><button class="button">Preview</button></form><p>Actual page in a '.$width.'px viewport.</p><iframe title="Product preview" style="width:'.$width.'px;height:850px;max-width:none;border:0" src="'.esc_url(add_query_arg('agst_qa',$section,get_permalink($id))).'"></iframe></div>';});});
add_action('wp_enqueue_scripts',function(){if(AGST_Storefront::active())wp_add_inline_style('agst-storefront','.agx-split-copy .agx-stats{grid-template-columns:repeat(2,minmax(0,1fr))!important}.agx-split-copy .agx-stat{min-width:0}.agx-split-copy .agx-stat-value .elementor-heading-title{overflow-wrap:normal;word-break:normal}');},110);
// Shortcode widgets (agx-shortcode) are skipped when their shortcode is not registered (e.g. the AR plugin is inactive on staging),
// so visitors never see raw "[ar-display id=…]" text; the shortcode stays in the Elementor data and renders where the plugin is active.
add_filter('elementor/frontend/widget/should_render',function($r,$w){if(!$r||!is_object($w)||!method_exists($w,'get_settings'))return $r;$st=$w->get_settings();if(strpos((string)($st['_css_classes']??''),'agx-shortcode')===false)return $r;$c=(string)($st['editor']??$st['shortcode']??'');if(preg_match('~\[([a-zA-Z0-9_-]+)~',$c,$m)&&!shortcode_exists($m[1]))return false;return $r;},10,2);

// Media probe (admins, read-only): best watermark-free source for gallery attachments.
// Image Watermark burns the mark into the files and keeps originals in uploads/iw-backup/<Y>/<m>/<name>.<ext>
// (the stem matches the attachment file, the extension may differ after WebP conversion). WordPress also keeps
// the pre-scaling original for "-scaled" uploads (metadata original_image).
final class AGST_MediaSource {
 static function clean($id){
  $up=wp_upload_dir();$rel=(string)get_post_meta($id,'_wp_attached_file',true);if($rel==='')return null;
  $dir=dirname($rel);$stem=pathinfo($rel,PATHINFO_FILENAME);$meta=wp_get_attachment_metadata($id);$c=[];
  foreach([$stem,preg_replace('~-scaled$~','',$stem)] as $s)foreach(['png','jpg','jpeg','webp','PNG','JPG','JPEG'] as $e)$c[]=['iw-backup',$up['basedir'].'/iw-backup/'.$dir.'/'.$s.'.'.$e];
  if(!empty($meta['original_image'])){$c[]=['iw-backup',$up['basedir'].'/iw-backup/'.$dir.'/'.$meta['original_image']];$c[]=['original',$up['basedir'].'/'.$dir.'/'.$meta['original_image']];}
  foreach($c as $x){if(is_file($x[1])){$sz=@getimagesize($x[1]);return ['kind'=>$x[0],'path'=>substr($x[1],strlen($up['basedir'])+1),'w'=>$sz[0]??0,'h'=>$sz[1]??0,'bytes'=>filesize($x[1])];}}
  return null;
 }
}
add_action('wp_ajax_agst_media_probe',function(){
 if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);
 $out=[];foreach(array_map('intval',explode(',',(string)($_POST['ids']??''))) as $id){if($id)$out[$id]=AGST_MediaSource::clean($id);}
 wp_send_json_success($out);
});

// ===== Product media (round 5): real project photos + videos under the product body =====
// Per product meta _agst_media = ['title'=>..,'intro'=>..,'images'=>[attachment ids],'videos'=>[['id'=>YouTube id,'title'=>..]]].
// Photos come from the /gallery FooGallery. Gallery files carry a burned-in watermark, so product pages use a
// clean copy generated from the untouched original (Image Watermark backup or WordPress pre-scaling original)
// into uploads/agst-media/ as a normal media-library attachment (meta _agst_clean_of = gallery attachment).
// The gallery itself is not changed. Editable per product in the "Project photos & videos" box.
final class AGST_Media {
 const META='_agst_media';
 /** Product-page alt/caption for a photo: agst_media_text map first (gallery originals keep their own fields), then the attachment. */
 static function text($id){static $m=null;if($m===null){$m=json_decode((string)get_option('agst_media_text','{}'),true);if(!is_array($m))$m=[];}return $m[(string)(int)$id]??null;}
 static function alt($id){$t=self::text($id);return $t&&($t['alt']??'')!==''?$t['alt']:(string)get_post_meta($id,'_wp_attachment_image_alt',true);}
 static function cap($id){$t=self::text($id);return $t&&($t['cap']??'')!==''?$t['cap']:(string)wp_get_attachment_caption($id);}

 static function boot(){
  add_action('add_meta_boxes_product',function(){add_meta_box('agst-media','Project photos & videos (below the product body)',[__CLASS__,'box'],'product','normal','high');});
  add_action('save_post_product',[__CLASS__,'save'],10,1);
  add_action('wp_ajax_agst_media_apply',[__CLASS__,'ajax_apply']);
 }
 static function get($pid){$m=get_post_meta($pid,self::META,true);if(!is_array($m))$m=[];return array_merge(['title'=>'','intro'=>'','images'=>[],'videos'=>[]],$m);}
 static function yt($s){return preg_match('~(?:youtu\.be/|v=|embed/|shorts/|^)([A-Za-z0-9_-]{11})(?:$|[?&#/\s])~',trim((string)$s),$m)?$m[1]:'';}
 static function images($pid){return array_values(array_filter(array_map('intval',(array)self::get($pid)['images']),function($id){return $id&&wp_attachment_is_image($id);}));}
 /* ---------- clean copies ---------- */
 static function clean_copy($src_id,$clean_rel,$alt='',$caption='',$ref=''){
  $src_id=(int)$src_id;
  $have=get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_agst_clean_of','meta_value'=>$src_id,'fields'=>'ids','numberposts'=>1]);
  if($have){$id=(int)$have[0];if($alt!=='')update_post_meta($id,'_wp_attachment_image_alt',$alt);return $id;}
  $up=wp_upload_dir();$src=$up['basedir'].'/'.ltrim(str_replace('..','',(string)$clean_rel),'/');if(!is_file($src))return new WP_Error('nosrc','missing '.$clean_rel);
  require_once ABSPATH.'wp-admin/includes/image.php';
  $ed=wp_get_image_editor($src);if(is_wp_error($ed))return $ed;
  if(method_exists($ed,'maybe_exif_rotate'))$ed->maybe_exif_rotate();
  $ed->resize(2048,2048,false);if(method_exists($ed,'set_quality'))$ed->set_quality(82);
  $att=(string)get_post_meta($src_id,'_wp_attached_file',true);$sub='agst-media/'.trim(dirname($att),'./');$dir=$up['basedir'].'/'.$sub;wp_mkdir_p($dir);
  $mime=$ed->supports_mime_type('image/webp')?'image/webp':'image/jpeg';
  $name=wp_unique_filename($dir,sanitize_file_name(preg_replace('~-scaled$~','',pathinfo($att,PATHINFO_FILENAME))).($mime==='image/webp'?'.webp':'.jpg'));
  $saved=$ed->save($dir.'/'.$name,$mime);if(is_wp_error($saved))return $saved;
  $title=get_the_title($src_id);
  $id=wp_insert_attachment(['post_mime_type'=>$saved['mime-type'],'post_title'=>$title!==''?$title:$name,'post_excerpt'=>$caption,'post_content'=>'','post_status'=>'inherit'],$saved['path']);
  if(is_wp_error($id)||!$id)return new WP_Error('insert','attachment insert failed');
  // Only the sizes the product page uses; skips the theme's many crops for these copies.
  $keep=function($s){return array_intersect_key($s,array_flip(['thumbnail','medium','medium_large','large']));};
  add_filter('intermediate_image_sizes_advanced',$keep,99);
  // Never re-watermark the clean copy (Image Watermark hooks metadata generation when it is active, e.g. on live).
  $iw=[];global $wp_filter;if(isset($wp_filter['wp_generate_attachment_metadata']))foreach($wp_filter['wp_generate_attachment_metadata']->callbacks as $prio=>$cbs)foreach($cbs as $k=>$cb){if(is_array($cb['function'])&&is_object($cb['function'][0])&&stripos(get_class($cb['function'][0]),'watermark')!==false){$iw[]=[$prio,$cb['function']];remove_filter('wp_generate_attachment_metadata',$cb['function'],$prio);}}
  wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$saved['path']));
  foreach($iw as $x)add_filter('wp_generate_attachment_metadata',$x[1],$x[0],2);
  remove_filter('intermediate_image_sizes_advanced',$keep,99);
  // Safety: the clean file must show the same photo as the gallery attachment (some originals on disk were overwritten
  // by other uploads with the same name). Compare with the attachment's own thumbnail; discard our copy on mismatch.
  $rh=preg_match('~^[01]{256}$~',(string)$ref)?$ref:(($tp=self::thumb_path($src_id))?self::dhash($tp):null);$d=is_file($saved['path'])?self::hdist($rh,self::dhash($saved['path'])):-1;
  if($d>80){wp_delete_attachment($id,true);return new WP_Error('mismatch','clean source is a different photo (distance '.$d.') for '.$clean_rel);}
  update_post_meta($id,'_wp_attachment_image_alt',$alt);update_post_meta($id,'_agst_clean_of',$src_id);update_post_meta($id,'_agst_clean_src',$clean_rel);update_post_meta($id,'_agst_clean_dist',$d);
  return $id;
 }
 static function thumb_path($id){$m=wp_get_attachment_metadata($id);$up=wp_upload_dir();$dir=$up['basedir'].'/'.dirname((string)get_post_meta($id,'_wp_attached_file',true));foreach(['medium','thumbnail','medium_large'] as $k){if(!empty($m['sizes'][$k]['file'])&&is_file($dir.'/'.$m['sizes'][$k]['file']))return $dir.'/'.$m['sizes'][$k]['file'];}return null;}
 /** 256-bit difference hash (GD); null when the image cannot be read. */
 static function dhash($file){if(!function_exists('imagecreatefromstring'))return null;$im=@imagecreatefromstring((string)file_get_contents($file));if(!$im)return null;$s=imagecreatetruecolor(17,16);imagecopyresampled($s,$im,0,0,0,0,17,16,imagesx($im),imagesy($im));$b='';
  for($y=0;$y<16;$y++){for($x=0;$x<16;$x++){$a=imagecolorat($s,$x,$y);$c=imagecolorat($s,$x+1,$y);$la=(($a>>16)&255)*299+(($a>>8)&255)*587+($a&255)*114;$lc=(($c>>16)&255)*299+(($c>>8)&255)*587+($c&255)*114;$b.=$la>$lc?'1':'0';}}imagedestroy($im);imagedestroy($s);return $b;}
 static function hdist($a,$b){if($a===null||$b===null||strlen($a)!==strlen($b))return -1;$n=0;for($i=0;$i<strlen($a);$i++)if($a[$i]!==$b[$i])$n++;return $n;}
 /** Resolve a gallery attachment on this site by its upload path (IDs differ between sites). */
 static function by_path($rel,$ref=''){global $wpdb;$ids=array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value=%s",$rel)));
  if(count($ids)<2||!preg_match('~^[01]{256}$~',(string)$ref))return $ids?$ids[0]:0;
  // Several attachments share this file path: take the one whose thumbnail is the photo the gallery shows.
  $best=$ids[0];$bd=999;foreach($ids as $id){$t=self::thumb_path($id);$d=$t?self::hdist($ref,self::dhash($t)):-1;if($d>=0&&$d<$bd){$bd=$d;$best=$id;}}return $best;}
 /** AJAX: payload {product_id:{title,intro,images:[{file,clean,alt,caption}],videos:[{id,title}]}} -> clean copies + meta. */
 static function ajax_apply(){
  if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);
  @set_time_limit(300);$data=json_decode(wp_unslash((string)($_POST['payload']??'')),true);if(!is_array($data))wp_send_json_error('bad payload');
  $out=[];
  foreach($data as $key=>$d){
   $pid=(int)$key;if(!$pid&&!empty($d['slug'])){$p=get_page_by_path((string)$d['slug'],OBJECT,'product');$pid=$p?$p->ID:0;}
   if(!$pid||get_post_type($pid)!=='product'){$out[$key]='no product';continue;}
   $ids=[];$err=[];
   foreach((array)($d['images']??[]) as $im){$ref=(string)($im['ref']??'');$src=self::by_path((string)$im['file'],$ref);if(!$src){$err[]='src? '.$im['file'];continue;}
    $id=self::clean_copy($src,(string)$im['clean'],(string)($im['alt']??''),(string)($im['caption']??''),$ref);if(is_wp_error($id)){$err[]=$id->get_error_message();continue;}$ids[]=$id;}
   $vids=[];foreach((array)($d['videos']??[]) as $v){$y=self::yt($v['id']??'');if($y)$vids[]=['id'=>$y,'title'=>sanitize_text_field((string)($v['title']??''))];}
   $cur=self::get($pid);
   update_post_meta($pid,self::META,['title'=>sanitize_text_field((string)($d['title']??$cur['title'])),'intro'=>sanitize_text_field((string)($d['intro']??$cur['intro'])),'images'=>($ids||!empty($d['replace']))?$ids:$cur['images'],'videos'=>array_key_exists('videos',$d)?$vids:$cur['videos'],'v'=>1]);
   $out[$pid]=['images'=>count($ids),'videos'=>count($vids),'errors'=>$err];
  }
  wp_send_json_success($out);
 }
 /* ---------- admin box ---------- */
 static function box($post){
  $m=self::get($post->ID);wp_enqueue_media();wp_nonce_field('agst-media','agst_media_nonce');
  echo '<p style="margin-top:0">Shown under the product body: a project photo gallery (with lightbox) and a video row. Drag to reorder. Use clean, real project photos of this system only.</p>';
  echo '<p><label>Section title<br><input type="text" class="widefat" name="agst_media_title" value="'.esc_attr($m['title']).'" placeholder="Real projects"></label></p>';
  echo '<p><label>Short intro<br><input type="text" class="widefat" name="agst_media_intro" value="'.esc_attr($m['intro']).'"></label></p>';
  echo '<input type="hidden" name="agst_media_images" id="agst-media-images" value="'.esc_attr(implode(',',self::images($post->ID))).'"><ul id="agst-media-list" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px">';
  foreach(self::images($post->ID) as $id){echo '<li data-id="'.$id.'" style="position:relative;cursor:move;margin:0">'.wp_get_attachment_image($id,'thumbnail',false,['style'=>'width:96px;height:96px;object-fit:cover;border-radius:6px;display:block']).'<button type="button" class="agst-media-x" aria-label="Remove photo" style="position:absolute;top:2px;right:2px;border:0;border-radius:50%;width:22px;height:22px;background:#000a;color:#fff;cursor:pointer">×</button></li>';}
  echo '</ul><p><button type="button" class="button" id="agst-media-add">Add photos</button></p>';
  $lines=[];foreach((array)$m['videos'] as $v)$lines[]='https://www.youtube.com/watch?v='.$v['id'].($v['title']!==''?' | '.$v['title']:'');
  echo '<p><label>Videos (one per line: YouTube link | title)<br><textarea class="widefat" rows="4" name="agst_media_videos">'.esc_textarea(implode("\n",$lines)).'</textarea></label></p>';
  ?><script>jQuery(function($){var L=$('#agst-media-list'),I=$('#agst-media-images');function sync(){I.val(L.children().map(function(){return $(this).data('id');}).get().join(','));}
  if($.fn.sortable)L.sortable({update:sync});L.on('click','.agst-media-x',function(){$(this).closest('li').remove();sync();});
  $('#agst-media-add').on('click',function(e){e.preventDefault();var f=wp.media({title:'Add project photos',library:{type:'image'},multiple:true,button:{text:'Add to product'}});f.on('select',function(){f.state().get('selection').each(function(a){a=a.toJSON();var u=(a.sizes&&a.sizes.thumbnail||a).url;L.append('<li data-id="'+a.id+'" style="position:relative;cursor:move;margin:0"><img src="'+u+'" style="width:96px;height:96px;object-fit:cover;border-radius:6px;display:block"><button type="button" class="agst-media-x" aria-label="Remove photo" style="position:absolute;top:2px;right:2px;border:0;border-radius:50%;width:22px;height:22px;background:#000a;color:#fff;cursor:pointer">×</button></li>');});sync();});f.open();});});</script><?php
 }
 static function save($pid){
  if(!isset($_POST['agst_media_nonce'])||!wp_verify_nonce($_POST['agst_media_nonce'],'agst-media')||!current_user_can('edit_post',$pid)||wp_is_post_revision($pid))return;
  $ids=array_values(array_filter(array_map('intval',explode(',',(string)($_POST['agst_media_images']??'')))));
  $vids=[];foreach(preg_split('~\R~',(string)wp_unslash($_POST['agst_media_videos']??'')) as $l){$parts=array_map('trim',explode('|',$l,2));$y=self::yt($parts[0]??'');if($y)$vids[]=['id'=>$y,'title'=>sanitize_text_field($parts[1]??'')];}
  $cur=self::get($pid);update_post_meta($pid,self::META,array_merge($cur,['title'=>sanitize_text_field(wp_unslash($_POST['agst_media_title']??'')),'intro'=>sanitize_text_field(wp_unslash($_POST['agst_media_intro']??'')),'images'=>$ids,'videos'=>$vids]));
 }
 /* ---------- front end ---------- */
 /** <img> with an explicit srcset (theme filters strip WordPress' own and its "large" size is only 350px). */
 static function img($id,$alt,$sizes,$eager=false){
  $meta=wp_get_attachment_metadata($id);$full=wp_get_attachment_image_url($id,'full');$base=trailingslashit(dirname($full));$set=[];
  foreach(['medium_large','large','medium'] as $k){if(!empty($meta['sizes'][$k]['file'])&&($meta['sizes'][$k]['width']??0)>=480)$set[(int)$meta['sizes'][$k]['width']]=$base.$meta['sizes'][$k]['file'];}
  if(!empty($meta['width']))$set[(int)$meta['width']]=$full;ksort($set);$src=$set?reset($set):$full;$ss=[];foreach($set as $w=>$u)$ss[]=esc_url($u).' '.$w.'w';
  return '<img src="'.esc_url($src).'" srcset="'.implode(', ',$ss).'" sizes="'.esc_attr($sizes).'" alt="'.esc_attr($alt).'" width="'.(int)($meta['width']??0).'" height="'.(int)($meta['height']??0).'" loading="'.($eager?'eager':'lazy').'" decoding="async">';
 }
 static function nav($pid,$page){$h='';if(self::images($pid))$h.='<a href="#agx-real">Projects</a>';if(self::videos($pid,$page))$h.='<a href="#agx-videos">Videos</a>';return $h;}
 static function videos($pid,$page){$out=[];foreach((array)self::get($pid)['videos'] as $v){if(empty($v['id'])||strpos((string)$page,$v['id'])!==false)continue;$out[$v['id']]=$v;}return array_values($out);}
 static function section($pid,$page){
  $m=self::get($pid);$ids=self::images($pid);$h='';
  if($ids){$n=count($ids);$title=$m['title']!==''?$m['title']:'Real projects';$intro=$m['intro']!==''?$m['intro']:'Completed installations by Aluglobus Aluminum Systems. Sizes, layouts and accessories vary by project.';
   $h.='<section class="agx-section agx-real" id="agx-real"><div class="agx-section-heading"><div><p class="agx-eyebrow">Real projects</p><h2>'.esc_html($title).'</h2></div><p>'.esc_html($intro).'</p></div><div class="agx-real-grid" data-lb-group="real">';
   foreach($ids as $i=>$id){$full=wp_get_attachment_image_url($id,'full');$alt=trim(AGST_Media::alt($id));$cap=AGST_Media::cap($id);
    $h.='<figure class="agx-real-item'.($i>=8?' is-more':'').'"><button type="button" class="agx-zoom" data-full="'.esc_url($full).'" data-caption="'.esc_attr($cap?:$alt).'" aria-label="Enlarge project photo '.($i+1).' of '.$n.'">'.self::img($id,$alt,$i===0?'(max-width:720px) 100vw, 50vw':'(max-width:720px) 50vw, 25vw',$i<3).'</button></figure>';}
   $h.='</div>'.($n>8?'<div class="agx-real-actions"><button type="button" class="agx-button agx-button-line agx-real-more" aria-expanded="false">Show all '.$n.' photos</button></div>':'').'</section>';}
  $vs=self::videos($pid,$page);
  if($vs){$h.='<section class="agx-section agx-vids" id="agx-videos"><div class="agx-section-heading"><div><p class="agx-eyebrow">Watch</p><h2>'.(count($vs)>1?'Videos':'Video').'</h2></div><p>Installation, product and factory videos from Aluglobus Aluminum Systems.</p></div><div class="agx-vid-grid">';
   foreach($vs as $v){$t=$v['title']!==''?$v['title']:'Aluglobus video';$h.='<button type="button" class="agx-vid" data-yt="'.esc_attr($v['id']).'" data-caption="'.esc_attr($t).'" aria-label="Play video: '.esc_attr($t).'"><span class="agx-vid-thumb"><img src="https://i.ytimg.com/vi/'.esc_attr($v['id']).'/hqdefault.jpg" alt="" onerror="this.style.visibility=\'hidden\'" loading="lazy" decoding="async" width="480" height="360"><span class="agx-vid-play" aria-hidden="true"></span></span><span class="agx-vid-title">'.esc_html($t).'</span></button>';}
   $h.='</div></section>';}
  return $h;
 }
}
AGST_Media::boot();

// ===== Related products in this system + "Before you order" checklist (round 5) =====
// Families live in option agst_families (JSON list: key,title,text,members[slugs],steps[]), uploaded with agst_families_save.
// Slugs keep it portable to live. A product shows its first family: real catalog products, linked, plus the checklist.
final class AGST_Related {
 static function boot(){add_action('wp_ajax_agst_families_save',function(){if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);$f=json_decode(wp_unslash((string)($_POST['families']??'')),true);if(!is_array($f))wp_send_json_error('bad json');update_option('agst_families',wp_json_encode($f),false);wp_send_json_success(count($f));});}
 static function family($pid){$slug=get_post_field('post_name',$pid);$f=json_decode((string)get_option('agst_families','[]'),true);foreach((array)$f as $x){if(in_array($slug,(array)($x['members']??[]),true))return $x;}return null;}
 static function section($pid){
  $f=self::family($pid);if(!$f)return '';$cards='';$n=0;
  foreach((array)$f['members'] as $s){if($n>=8)break;$p=get_page_by_path($s,OBJECT,'product');if(!$p||$p->ID==$pid||$p->post_status!=='publish')continue;$prod=wc_get_product($p->ID);if(!$prod||!$prod->is_visible())continue;$n++;
   $img=get_the_post_thumbnail_url($p->ID,'woocommerce_thumbnail')?:wc_placeholder_img_src();
   $cards.='<a class="agx-rel-card" href="'.esc_url(get_permalink($p->ID)).'"><span class="agx-rel-img"><img src="'.esc_url($img).'" alt="'.esc_attr($prod->get_name()).'" loading="lazy" decoding="async"></span><span class="agx-rel-name">'.esc_html($prod->get_name()).'</span><span class="agx-rel-price">'.wp_kses_post($prod->get_price_html()?:'Price on request').'</span></a>';}
  $steps='';foreach((array)($f['steps']??[]) as $i=>$t)$steps.='<li><span>'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span><p>'.esc_html($t).'</p></li>';
  if($cards===''&&$steps==='')return '';
  return '<section class="agx-section agx-related" id="agx-related"><div class="agx-section-heading"><div><p class="agx-eyebrow">Complete the system</p><h2>'.esc_html($f['title']).'</h2></div><p>'.esc_html($f['text']).'</p></div>'
   .($cards?'<div class="agx-rel-grid">'.$cards.'</div>':'')
   .($steps?'<div class="agx-order"><h3>Before you order</h3><ol class="agx-order-steps">'.$steps.'</ol></div>':'').'</section>';
 }
}
AGST_Related::boot();
// Admin read-only helper: selected meta for a list of posts (QA of clean copies).
add_action('wp_ajax_agst_meta_get',function(){if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);$keys=array_filter(explode(',',(string)($_POST['keys']??'')));$out=[];foreach(array_map('intval',explode(',',(string)($_POST['ids']??''))) as $id){if(!$id)continue;foreach($keys as $k)$out[$id][$k]=get_post_meta($id,$k,true);}wp_send_json_success($out);});
// Reject a clean copy that does not match its gallery photo: it is no longer reused or shown (kept in the library for audit).
add_action('wp_ajax_agst_media_reject',function(){if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);$out=[];foreach(array_map('intval',explode(',',(string)($_POST['ids']??''))) as $id){$src=get_post_meta($id,'_agst_clean_of',true);if($src===''){$out[$id]='not a clean copy';continue;}update_post_meta($id,'_agst_clean_rejected',$src);delete_post_meta($id,'_agst_clean_of');
 foreach(get_posts(['post_type'=>'product','post_status'=>'any','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_agst_media']) as $pid){$m=get_post_meta($pid,'_agst_media',true);if(is_array($m)&&in_array($id,array_map('intval',(array)$m['images']),true)){$m['images']=array_values(array_diff(array_map('intval',$m['images']),[$id]));update_post_meta($pid,'_agst_media',$m);$out[$id][]=$pid;}}}
 wp_send_json_success($out);});

// ===== Product page v2 (2026-10): light, photo-led layout =====
// Enabled per product (meta _agst_tpl_v2=1), for all (option agst_v2='all'), or previewed on staging with ?v2=1 (?v1=1 forces v1).
// Image classes (R real photo, C product render/cut-out, D drawing, A AI scene, X junk) come from option agst_img_classes
// (path => class); real project photos come from _agst_media. Content and editing stay in Elementor + the media box.
final class AGST_V2 {
 static function on($pid){
  if(isset($_GET['v1']))return false;
  if(isset($_GET['v2'])&&parse_url(home_url(),PHP_URL_HOST)==='globusgates.online')return true;
  return get_option('agst_v2','')==='all'||(bool)get_post_meta($pid,'_agst_tpl_v2',true);
 }
 static function classes(){static $c=null;if($c===null){$c=json_decode((string)get_option('agst_img_classes','{}'),true);if(!is_array($c))$c=[];}return $c;}
 static function key($u){$p=(string)parse_url(html_entity_decode((string)$u,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_PATH);return preg_replace('~-\d+x\d+(?=\.\w+$)~','',$p);}
 static function cls($u){$c=self::classes();return $c[self::key($u)]??'';}
 static function is_kit($p){$n=strtolower($p->get_name());return (bool)preg_match('~kit|gate|fence|pergola|patio|roof system|cladding|clad1|louver~',$n)&&!preg_match('~hinge|bracket|screw|spacer|cap\b|plug|stopper|latch|bolt|anchor|wheel|track|roller|catcher|post kit|slat box|frame kit only~',$n);}
 /** Hero media: real project photos first for kits, product images first for parts; AI scenes only as a last resort. */
 static function hero($p,$model,$body=''){
  $real=[];if(class_exists('AGST_Media'))foreach(AGST_Media::images($p->get_id()) as $id)$real[]=['url'=>wp_get_attachment_url($id),'thumb'=>wp_get_attachment_image_url($id,'medium_large')?:wp_get_attachment_url($id),'alt'=>AGST_Media::alt($id),'tag'=>'Real project'];
  if(count($real)<4&&$body&&preg_match_all('~<img[^>]+src="([^"]+)"~i',$body,$mm)){foreach($mm[1] as $u){if(self::cls($u)!=='R')continue;$full=preg_replace('~-\d+x\d+(?=\.\w+$)~','',$u);$real[]=['url'=>$full,'thumb'=>$u,'alt'=>'','tag'=>'Real project'];if(count($real)>=8)break;}}
  $prod=[];$ai=[];foreach($model['hero'] as $im){$c=self::cls($im['url']);$it=['url'=>$im['url'],'thumb'=>$im['url'],'alt'=>$im['alt']??'','tag'=>$c==='R'?'Real project':''];if($c==='X')continue;if($c==='A')$ai[]=$it;elseif($c==='R')array_unshift($real,$it);else $prod[]=$it;}
  $list=self::is_kit($p)?array_merge(array_slice($real,0,10),$prod):array_merge($prod,array_slice($real,0,8));
  if(count($list)<2)$list=array_merge($list,array_slice($ai,0,3));
  $seen=[];$out=[];foreach($list as $it){$k=self::key($it['url']);if(isset($seen[$k])||!$it['url'])continue;$seen[$k]=1;$out[]=$it;}return array_slice($out,0,14);
 }
 static function spec_pairs($specs){$o=[];foreach((array)$specs as $s){$s=trim(wp_strip_all_tags((string)$s));if($s==='')continue;if(preg_match('~^([^:]{2,40}):\s*(.+)$~u',$s,$m))$o[]=[trim($m[1]),trim($m[2])];else $o[]=['',$s];}return $o;}
 static function highlights($pairs){$want=['~material~i'=>'material','~finish|coat~i'=>'finish','~color|colour~i'=>'color','~size|dimension|length|kit size|height|width~i'=>'size','~gap|slat~i'=>'slat','~install|usage|use|fits~i'=>'use','~roof|louver|motor~i'=>'roof'];$out=[];$used=[];
  foreach($want as $re=>$icon){foreach($pairs as $i=>$pr){if(isset($used[$i])||$pr[0]==='')continue;if(preg_match($re,$pr[0])&&mb_strlen($pr[1])<=60){$out[]=[$icon,$pr[0],$pr[1]];$used[$i]=1;break;}}if(count($out)>=4)break;}return $out;}
 static function icon($n){$p=['material'=>'<path d="M4 7l8-4 8 4-8 4-8-4z"/><path d="M4 12l8 4 8-4"/><path d="M4 17l8 4 8-4"/>','finish'=>'<circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 0 16"/>','color'=>'<path d="M12 3a9 9 0 1 0 0 18c1.2 0 2-.8 2-2 0-1.4-1.2-1.6-1.2-3 0-1 .8-1.7 1.8-1.7H17a4 4 0 0 0 4-4C21 6.6 17 3 12 3z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>','size'=>'<path d="M3 17L17 3l4 4L7 21z"/><path d="M7 13l2 2M10 10l2 2M13 7l2 2"/>','slat'=>'<path d="M4 5h16M4 10h16M4 15h16M4 20h16"/>','use'=>'<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>','roof'=>'<path d="M3 11l9-6 9 6"/><path d="M5 10v9h14v-9"/>','truck'=>'<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>','factory'=>'<path d="M3 21V10l6 4V10l6 4V6h6v15z"/>','chat'=>'<path d="M4 5h16v11H8l-4 4z"/>','check'=>'<path d="M5 12l4 4 10-10"/>','play'=>'<path d="M8 5l11 7-11 7z"/>'];
  return '<svg class="agv-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.($p[$n]??$p['check']).'</svg>';}
 static function yt($u){return preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|watch\?v=))([a-zA-Z0-9_-]{11})~',(string)$u,$m)?$m[1]:'';}
 static function render($model){
  extract($model);$pid=$p->get_id();$title=$r['title'];$price=$p->get_price_html();$el0=class_exists('AGST_ElBuild')?AGST_ElBuild::show($pid):null;$hero=self::hero($p,$model,(string)$el0);$pairs=self::spec_pairs($r['specs']);$hl=self::highlights($pairs);
  $el=$el0;$has_el=$el!==null&&(class_exists('AGST_ElBuild')&&AGST_ElBuild::preview($pid)||trim(wp_strip_all_tags((string)$el,true))!==''||stripos((string)$el,'<img')!==false);
  $media=class_exists('AGST_Media')?AGST_Media::get($pid):['title'=>'','intro'=>'','videos'=>[]];$real_ids=class_exists('AGST_Media')?AGST_Media::images($pid):[];$real_ids=array_values(array_filter($real_ids,function($id)use($el){$u=wp_get_attachment_url($id);return !$u||strpos((string)$el,pathinfo($u,PATHINFO_FILENAME))===false;}));
  // videos: product's own first, then project videos; skip any already inside the body
  $vids=[];foreach($videos as $v){$y=self::yt($v['url']);$k=$y?:$v['url'];if($y&&strpos((string)$el,$y)!==false)continue;$vids[$k]=['yt'=>$y,'url'=>$v['url'],'title'=>'Product video'];}
  foreach((array)$media['videos'] as $v){if(empty($v['id'])||strpos((string)$el,$v['id'])!==false)continue;$vids[$v['id']]=['yt'=>$v['id'],'url'=>'','title'=>$v['title']?:'Aluglobus video'];}$vids=array_values($vids);
  // supporting images: real ones join the projects grid, renders/drawings go to the views panel, AI scenes are dropped
  $views=[];$extra_real=[];foreach($supporting as $im){$c=self::cls($im['url']);if($c==='R')$extra_real[]=$im;elseif($c==='C'||$c==='D'||($c===''&&!$real_ids))$views[]=$im;}
  $soon=class_exists('AGST_ShopFront')?AGST_ShopFront::soon($pid):'';$contact=get_page_by_path('contact-us');$quote=home_url('/online-quote/');
  $close=$real_ids?wp_get_attachment_image_url(end($real_ids),'large'):'';if(!$close)foreach($hero as $h)if($h['tag']){$close=$h['url'];break;}
  $nav=[];if($has_el)$nav['agv-overview']='Overview';if($real_ids||$extra_real)$nav['agv-projects']='Projects';if($vids)$nav['agv-videos']='Videos';$nav['agv-specs']='Specifications';if($r['faq']&&stripos((string)$el,'elementor-toggle')===false)$nav['agv-faq']='FAQ';
 ?>
 <main class="agv" id="agx-product" data-product-id="<?php echo (int)$pid;?>">
 <nav class="agv-crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url(AGST_Catalog::local_url(wc_get_page_permalink('shop')));?>">Shop</a><?php $pc=(int)get_post_meta($pid,'_yoast_wpseo_primary_product_cat',true);$t=$pc?get_term($pc,'product_cat'):null;if($t&&!is_wp_error($t)):?><span aria-hidden="true">›</span><a href="<?php echo esc_url(get_term_link($t));?>"><?php echo esc_html($t->name);?></a><?php endif;?><span aria-hidden="true">›</span><span aria-current="page"><?php echo esc_html($title);?></span></nav>
 <div class="agx-notices"><?php if(function_exists('wc_print_notices'))wc_print_notices();?></div>
 <section class="agv-hero" id="agx-configure">
  <div class="agv-media" data-lb-group="hero">
   <div class="agv-stage" tabindex="0" aria-roledescription="carousel" aria-label="Product photos">
    <?php foreach($hero as $i=>$h):?><figure class="agv-slide"><button type="button" class="agv-zoom" data-full="<?php echo esc_url($h['url']);?>" data-caption="<?php echo esc_attr($h['alt']?:$title);?>" aria-label="Enlarge photo <?php echo $i+1;?> of <?php echo count($hero);?>"><img src="<?php echo esc_url($i<2?$h['url']:$h['thumb']);?>" alt="<?php echo esc_attr($h['alt']?:$title);?>" <?php echo $i===0?'fetchpriority="high"':'loading="lazy"';?> decoding="async"></button><?php if($h['tag']):?><span class="agv-tag"><?php echo esc_html($h['tag']);?></span><?php endif;?></figure><?php endforeach;?>
    <?php if(!$hero):?><div class="agv-pending">Product image coming soon</div><?php endif;?>
   </div>
   <?php if(count($hero)>1):?><button type="button" class="agv-arrow agv-prev" aria-label="Previous photo">‹</button><button type="button" class="agv-arrow agv-next" aria-label="Next photo">›</button><span class="agv-count"><b>1</b> / <?php echo count($hero);?></span>
   <div class="agv-thumbs" aria-label="Choose photo"><?php foreach($hero as $i=>$h):?><button type="button" aria-label="Show photo <?php echo $i+1;?>" aria-current="<?php echo $i===0?'true':'false';?>"><img src="<?php echo esc_url($h['thumb']);?>" alt="" loading="lazy" decoding="async"></button><?php endforeach;?></div><?php endif;?>
   <?php if(count($hl)>=2):?><div class="agv-highlights agv-hl-desk" aria-label="Key facts"><?php foreach($hl as $h):?><div class="agv-hl"><?php echo self::icon($h[0]);?><span class="agv-hl-label"><?php echo esc_html($h[1]);?></span><span class="agv-hl-value"><?php echo esc_html($h[2]);?></span></div><?php endforeach;?></div><?php endif;?>
  </div>
  <div class="agv-buy" id="agv-buy">
   <p class="agv-kicker"><?php echo esc_html($r['family']);?></p>
   <h1 class="agv-title"><?php echo esc_html($title);?></h1>
   <?php if(!empty($r['lead'])):?><p class="agv-lead"><?php echo esc_html($r['lead']);?></p><?php endif;?>
   <?php if($hl):?><ul class="agv-chips"><?php foreach(array_slice($hl,0,3) as $h):?><li><?php echo esc_html($h[2]);?></li><?php endforeach;?></ul><?php endif;?>
   <div class="agv-price agx-price" aria-live="polite"><?php echo $price?:'Price on request';?></div>
   <?php if($soon):?><p class="agv-soon"><b>Coming soon</b> · <?php echo esc_html($soon==='all'?'please confirm availability before ordering.':$soon.' coming soon.');?></p><?php endif;?>
   <div class="agv-cart agx-cart"><?php global $product;if($product&&!$product->is_purchasable()){echo '<a class="agv-btn agv-btn--primary" href="'.esc_url($quote).'">Request a quote</a>';}else{woocommerce_template_single_add_to_cart();}?></div>
   <div class="agv-buy-links"><a class="agv-btn agv-btn--ghost" href="<?php echo esc_url($quote);?>">Request a project quote</a><?php if($contact):?><a class="agv-link" href="<?php echo esc_url(get_permalink($contact));?>">Talk to a specialist →</a><?php endif;?></div>
   <ul class="agv-trust"><li><?php echo self::icon('factory');?><span><b>Factory direct</b> from Aluglobus Aluminum Systems</span></li><li><?php echo self::icon('truck');?><span><b>Nationwide shipping</b> across the U.S.</span></li><li><?php echo self::icon('chat');?><span><b>Project support</b> for homeowners and trade pros</span></li></ul>
  </div>
 </section>
 <?php if(count($hl)>=2):?><section class="agv-highlights agv-hl-mob" aria-label="Key facts"><?php foreach($hl as $h):?><div class="agv-hl"><?php echo self::icon($h[0]);?><span class="agv-hl-label"><?php echo esc_html($h[1]);?></span><span class="agv-hl-value"><?php echo esc_html($h[2]);?></span></div><?php endforeach;?></section><?php endif;?>
 <nav class="agv-nav" aria-label="Page sections"><div class="agv-nav-in"><?php foreach($nav as $id=>$l):?><a href="#<?php echo esc_attr($id);?>"><?php echo esc_html($l);?></a><?php endforeach;?><a class="agv-nav-cta" href="#agv-buy"><?php echo $price?wp_strip_all_tags($price):'Get a quote';?> · Buy</a></div></nav>
 <?php if($has_el):?><div class="agv-body agx-el" id="agv-overview"><?php echo $el;?></div><?php endif;?>
 <?php if($real_ids||$extra_real):$n=count($real_ids)+count($extra_real);?>
 <section class="agv-sec agv-projects" id="agv-projects"><div class="agv-wrap"><div class="agv-head"><p class="agv-kicker">Real projects</p><h2><?php echo esc_html($media['title']?:'Installed by our customers');?></h2><p><?php echo esc_html($media['intro']?:'Completed installations by Aluglobus Aluminum Systems. Sizes, layouts and accessories vary by project.');?></p></div>
  <div class="agv-pgrid" data-lb-group="projects"><?php $i=0;foreach($real_ids as $id){$alt=AGST_Media::alt($id);echo '<figure class="agv-pi'.($i>=7?' is-more':'').'"><button type="button" class="agv-zoom" data-full="'.esc_url(wp_get_attachment_url($id)).'" data-caption="'.esc_attr(AGST_Media::cap($id)?:$alt).'" aria-label="Enlarge project photo '.($i+1).'">'.AGST_Media::img($id,$alt,$i===0?'(max-width:720px) 100vw, 50vw':'(max-width:720px) 50vw, 25vw',false).'</button></figure>';$i++;}
   foreach($extra_real as $im){echo '<figure class="agv-pi'.($i>=7?' is-more':'').'"><button type="button" class="agv-zoom" data-full="'.esc_url($im['url']).'" data-caption="'.esc_attr($im['alt']?:$title).'"><img src="'.esc_url($im['url']).'" alt="'.esc_attr($im['alt']?:$title).'" loading="lazy" decoding="async"></button></figure>';$i++;}?></div>
  <?php if($n>7):?><div class="agv-center"><button type="button" class="agv-btn agv-btn--ghost agv-more" aria-expanded="false" data-more="Show all <?php echo $n;?> photos" data-less="Show fewer photos">Show all <?php echo $n;?> photos</button></div><?php endif;?></div></section>
 <?php endif;?>
 <?php if($vids):$v0=$vids[0];?>
 <section class="agv-sec agv-videos" id="agv-videos"><div class="agv-wrap"><div class="agv-head"><p class="agv-kicker">Watch</p><h2>See it built and installed</h2><p>Installation, product and factory videos from Aluglobus Aluminum Systems.</p></div>
  <div class="agv-vfeature"><?php if($v0['yt']):?><button type="button" class="agv-vid agv-vid--big" data-yt="<?php echo esc_attr($v0['yt']);?>" data-caption="<?php echo esc_attr($v0['title']);?>"><img src="https://i.ytimg.com/vi/<?php echo esc_attr($v0['yt']);?>/maxresdefault.jpg" onerror="this.onerror=null;this.src='https://i.ytimg.com/vi/<?php echo esc_attr($v0['yt']);?>/hqdefault.jpg'" alt="" loading="lazy"><span class="agv-play"><?php echo self::icon('play');?></span><span class="agv-vtitle"><?php echo esc_html($v0['title']);?></span></button><?php else:?><video controls playsinline preload="none" src="<?php echo esc_url($v0['url']);?>"></video><?php endif;?>
  <?php if(count($vids)>1):?><div class="agv-vlist"><?php foreach(array_slice($vids,1,6) as $v):if($v['yt']):?><button type="button" class="agv-vid" data-yt="<?php echo esc_attr($v['yt']);?>" data-caption="<?php echo esc_attr($v['title']);?>"><span class="agv-vthumb"><img src="https://i.ytimg.com/vi/<?php echo esc_attr($v['yt']);?>/mqdefault.jpg" alt="" loading="lazy"><span class="agv-play agv-play--sm"><?php echo self::icon('play');?></span></span><span class="agv-vtitle"><?php echo esc_html($v['title']);?></span></button><?php else:?><video controls playsinline preload="none" src="<?php echo esc_url($v['url']);?>"></video><?php endif;endforeach;?></div><?php endif;?></div></div></section>
 <?php endif;?>
 <section class="agv-sec agv-specs" id="agv-specs"><div class="agv-wrap agv-specs-grid">
  <div><p class="agv-kicker">Specifications</p><h2>Technical details</h2><p class="agv-muted">Check sizes, material and configuration against your project before ordering.</p>
   <?php if(!empty($r['inclusions'])):?><div class="agv-card agv-incl"><h3>What's included</h3><ul class="agv-checks"><?php foreach($r['inclusions'] as $s):?><li><?php echo esc_html(preg_replace('/^\d+[.)]\s*/','',$s));?></li><?php endforeach;?></ul></div><?php endif;?>
  </div>
  <div><dl class="agv-dl"><?php foreach($pairs as $pr):?><?php if($pr[0]===''):?><div class="agv-dl-note"><dd><?php echo esc_html($pr[1]);?></dd></div><?php else:?><div><dt><?php echo esc_html($pr[0]);?></dt><dd><?php echo esc_html($pr[1]);?></dd></div><?php endif;?><?php endforeach;?></dl>
   <?php if($views):?><details class="agv-views"><summary><span>Product views &amp; drawings</span><small><?php echo count($views);?> images</small></summary><div class="agv-vgrid" data-lb-group="views"><?php foreach($views as $im):?><button type="button" class="agv-zoom" data-full="<?php echo esc_url($im['url']);?>" data-caption="<?php echo esc_attr($im['alt']?:$title);?>"><img src="<?php echo esc_url($im['url']);?>" alt="<?php echo esc_attr($im['alt']?:$title);?>" loading="lazy" decoding="async"></button><?php endforeach;?></div></details><?php endif;?>
  </div></div></section>
 <?php if(class_exists('AGST_Related'))echo str_replace(['agx-section agx-related','agx-section-heading'],['agv-sec agv-related','agv-head'],AGST_Related::section($pid));?>
 <?php $body_faq=stripos((string)$el,'elementor-toggle')!==false;if($r['faq']&&!$body_faq):?><section class="agv-sec agv-faq" id="agv-faq"><div class="agv-wrap agv-faq-grid"><div><p class="agv-kicker">FAQ</p><h2>Questions, answered</h2><div class="agv-card agv-help"><?php echo self::icon('chat');?><h3>Still deciding?</h3><p>Send your measurements and photos. Our team will help you choose the right system and parts.</p><a class="agv-btn agv-btn--primary" href="<?php echo esc_url($quote);?>">Request a quote</a><?php if($contact):?><a class="agv-link" href="<?php echo esc_url(get_permalink($contact));?>">Contact us →</a><?php endif;?></div></div>
  <div class="agv-acc"><?php foreach($r['faq'] as $qa):?><details><summary><?php echo esc_html($qa[0]);?></summary><div><?php echo wpautop(esc_html($qa[1]));?></div></details><?php endforeach;?></div></div></section><?php endif;?>
 <section class="agv-close"<?php if($close):?> style="--agv-close:url('<?php echo esc_url($close);?>')"<?php endif;?>><div class="agv-wrap"><p class="agv-kicker">Ready when you are</p><h2>Ready to start your project?</h2><p>Order online or send your project details for a factory-direct quote.</p><div class="agv-close-btns"><a class="agv-btn agv-btn--primary" href="#agv-buy">Choose options</a><a class="agv-btn agv-btn--light" href="<?php echo esc_url($quote);?>">Request a quote</a></div></div></section>
 <div class="agv-bar" aria-hidden="true"><?php if($hero):?><img src="<?php echo esc_url($hero[0]['thumb']);?>" alt=""><?php endif;?><div><b><?php echo esc_html($title);?></b><span><?php echo $price?wp_strip_all_tags($price):'Price on request';?></span></div><a class="agv-btn agv-btn--primary" href="#agv-buy" tabindex="-1"><?php echo $p->is_purchasable()?'Buy now':'Get a quote';?></a></div>
 </main>
 <?php }
}
// Admin: save whitelisted presentation options (image classes, v2 switch).
add_action('wp_ajax_agst_option_save',function(){if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);$k=(string)($_POST['key']??'');if(!in_array($k,['agst_img_classes','agst_v2'],true))wp_send_json_error('key');$v=wp_unslash((string)($_POST['value']??''));if($k==='agst_img_classes'&&!is_array(json_decode($v,true)))wp_send_json_error('json');update_option($k,$v,false);wp_send_json_success([$k=>strlen($v)]);});

// ===== Body v2: real photos through the page, AI images out, drawings in one panel =====
// Applied to the page spec right before the Elementor build when the product uses v2 (or option agst_v2='all').
// Real photos = the product's verified project photos (_agst_media) plus body images classed R.
final class AGST_V2Build {
 static function enabled($pid){return get_option('agst_v2','')==='all'||(bool)get_post_meta($pid,'_agst_tpl_v2',true);}
 static function c($src){return class_exists('AGST_V2')?AGST_V2::cls($src):'';}
 static function textish($sec){foreach((array)($sec['parts']??[]) as $p){if(!in_array($p['type']??'',['text','checklist','chips','note','buttons','stats'],true))return false;}return true;}

 /** Extra sections for thin pages, written only from this product's own spec lines and options (no invented facts). */
 static function facts($pid){$sp=json_decode((string)get_post_meta($pid,'_agst_seo_specs',true),true);$o=[];foreach((array)$sp as $l){$l=trim(wp_strip_all_tags((string)$l));if(preg_match('~^([^:]{2,40}):\s*(.+)$~u',$l,$m))$o[strtolower(trim($m[1]))]=trim($m[2]);}return $o;}
 static function pick($f,$re){foreach($f as $k=>$v)if(preg_match($re,$k))return $v;return '';}
 static function words($spec){$t='';foreach($spec as $s){$t.=' '.($s['title']??'').' '.wp_strip_all_tags((string)($s['text']??''));foreach((array)($s['parts']??[]) as $p)$t.=' '.wp_strip_all_tags(wp_json_encode($p));}return str_word_count($t);}
 static function extra($pid,$have=''){
  $p=wc_get_product($pid);if(!$p)return [];$f=self::facts($pid);$name=$p->get_name();$out=[];
  $mat=self::pick($f,'~^material~');$fin=self::pick($f,'~finish|coating~');$col=self::pick($f,'~^colou?rs?$~');$thk=self::pick($f,'~thick|wall~');
  $opts=[];foreach($p->get_attributes() as $a){if(!$a->get_variation())continue;$vals=$a->is_taxonomy()?wc_get_product_terms($pid,$a->get_name(),['fields'=>'names']):$a->get_options();if($vals)$opts[wc_attribute_label($a->get_name())]=implode(', ',$vals);}
  if($mat||$fin){$t='<p>'.esc_html($name).' is made from '.esc_html(strtolower($mat?:'aluminum')).($fin?', finished with '.esc_html($fin):'').($col?' in '.esc_html(strtolower($col)):'').'.</p>';
   $blob=strtolower($mat.' '.$fin);
   if(strpos($blob,'6063')!==false)$t.='<p>6063-T6 is an architectural aluminum alloy made for extruded profiles. It gives clean, consistent shapes and takes a powder coating evenly, which is why it is widely used for fences, gates and other exterior building products.</p>';
   if(strpos($blob,'2604')!==false)$t.='<p>AAMA 2604 is the American Architectural Manufacturers Association specification for high-performance organic coatings, powder coatings included, on aluminum extrusions. It sets test requirements for outdoor color and gloss retention, chalking and corrosion resistance.</p>';
   if(strpos($blob,'stainless')!==false)$t.='<p>Stainless steel is used for exterior fasteners because it resists rust outdoors.</p>';
   $st=[];foreach([['Material',$mat],['Finish',$fin],['Color',$col?:($opts['Color']??'')],['Wall thickness',$thk]] as $x)if($x[1]!=='')$st[]=['label'=>$x[0],'value'=>mb_strimwidth($x[1],0,60,'…')];
   if(preg_match('~6063|2604|stainless~',$blob))$out[]=['layout'=>'stack','eyebrow'=>'Material & finish','title'=>'What it is made of','text'=>$t,'parts'=>$st?[['type'=>'stats','items'=>array_slice($st,0,4)]]:[]];}
  $use=self::pick($f,'~^(usage|use|used with|application)~');$fits=self::pick($f,'~^(fits|compatible)~');
  if(($use||$fits)&&!preg_match('~where (it|this).{0,20}(used|goes|fits)|applications?\b|uses?\b~i',$have)){$items=[];if($use)foreach(preg_split('~\s*[,;]\s*~',$use) as $u)if(mb_strlen($u)>2)$items[]=ucfirst($u);
   $out[]=['layout'=>'stack','eyebrow'=>'Where it is used','title'=>'Where this part goes','text'=>'<p>'.($fits?'Fits: '.esc_html($fits).'. ':'').($use?'Listed use: '.esc_html($use).'.':'').'</p>','parts'=>$items&&count($items)>1?[['type'=>'checklist','items'=>array_slice($items,0,8)]]:[]];}
  $tips=[];$sold=self::pick($f,'~^sold|pack|quantity~');if($sold)$tips[]='Sold as: '.$sold.'.';$len=self::pick($f,'~^(length|size|dimensions)~');if($len)$tips[]='Size: '.$len.'.';
  foreach($opts as $k=>$v)$tips[]=$k.' options: '.$v.'.';
  foreach($f as $k=>$v)if(preg_match('~not included|sold separately|anchors|alternative|optional~i',$k.' '.$v)&&count($tips)<7)$tips[]=ucfirst($k).': '.$v.'.';
  if($p->get_meta('_agst_coming_soon'))$tips[]='Marked coming soon: confirm availability before ordering.';
  if(count($tips)>=2)$out[]=['layout'=>'stack','eyebrow'=>'Ordering','title'=>'Ordering tips','parts'=>[['type'=>'checklist','items'=>array_values(array_unique($tips))]]];
  return $out;
 }
 /** Editor notes left in the source copy ("Use this video section to show customers how...") read as customer copy. */
 static function copyfix($spec){array_walk_recursive($spec,function(&$v){if(!is_string($v)||stripos($v,'use this')===false&&stripos($v,'show customers')===false)return;
   $v=preg_replace(['~Use this video section to show customers how~i','~Use this section to help contractors and serious buyers understand the~i','~Use this section to show customers~i','~\bShow customers (the |how )?~'],['Watch how','Contractors and serious buyers can review the','See','See $1'],$v);});return $spec;}
 static function transform($spec,$pid){
  if(!is_array($spec)||!$spec)return $spec;
  $spec=self::copyfix($spec);
  $have='';foreach($spec as $x)$have.=' '.($x['eyebrow']??'').' '.($x['title']??'');
  // the v2 specifications section lists every spec line, so body sections that are only a spec table go
  $spec=array_values(array_filter($spec,function($x){$pt=array_column((array)($x['parts']??[]),'type');return !($pt&&!array_diff($pt,['specs'])&&preg_match('~spec~i',($x['title']??'').' '.($x['eyebrow']??''))&&trim(wp_strip_all_tags((string)($x['text']??'')))==='');}));
  if(self::words($spec)<650){$add=self::extra($pid,$have);if($add){$cta=null;if(($spec[count($spec)-1]['layout']??'')==='cta')$cta=array_pop($spec);$spec=array_merge($spec,$add);if($cta)$spec[]=$cta;}}
  // pool of real photos not already in the body
  $pool=[];$used=[];
  foreach($spec as $s){if(!empty($s['media']['src']))$used[AGST_V2::key($s['media']['src'])]=1;foreach((array)($s['parts']??[]) as $p)foreach((array)($p['images']??[]) as $im)if(!empty($im['src']))$used[AGST_V2::key($im['src'])]=1;}
  // body takes photos from the end of the list (the hero carousel starts from the front); bands prefer landscape
  if(class_exists('AGST_Media'))foreach(array_reverse(AGST_Media::images($pid)) as $id){$u=wp_get_attachment_url($id);if(!$u||isset($used[AGST_V2::key($u)]))continue;$m=wp_get_attachment_metadata($id);$pool[]=['src'=>$u,'id'=>$id,'alt'=>AGST_Media::alt($id),'caption'=>AGST_Media::cap($id),'land'=>($m['width']??0)>=($m['height']??1)];}
  $take=function($land=false)use(&$pool){if($land){foreach($pool as $i=>$x){if(!empty($x['land'])){array_splice($pool,$i,1);return $x;}}return null;}return array_shift($pool);};
  $views=[];$out=[];
  foreach($spec as $s){
   $layout=$s['layout']??'stack';
   $label=strtolower(($s['eyebrow']??'').' '.($s['title']??''));
   if(preg_match('~drawing|diagram|technical render|reference graphic|exploded|cad\b~',$label)){if(!empty($s['media']['src']))$views[]=$s['media'];foreach((array)($s['parts']??[]) as $p)foreach((array)($p['images']??[]) as $im)$views[]=$im;continue;}
   if($layout==='split'&&!empty($s['media']['src'])){$k=self::c($s['media']['src']);
    if($k==='A'||$k==='D'||$k==='X'){if($k==='D')$views[]=$s['media'];$r=$take();if($r){$s['media']=$r;}else{$s['layout']='stack';unset($s['media']);}}}
   $parts=[];foreach((array)($s['parts']??[]) as $p){$t=$p['type']??'';
    if($t==='gallery'){$keep=[];foreach((array)$p['images'] as $im){$k=self::c($im['src']??'');if($k==='A'||$k==='X')continue;if($k==='D'||($k==='C'&&count((array)$p['images'])>2)){$views[]=$im;continue;}$keep[]=$im;}if(!$keep)continue;$p['images']=$keep;if(count($keep)<($p['cols']??3))$p['cols']=max(1,min(count($keep),$p['cols']??3));}
    if($t==='video'&&$pool){$ph=$pool[count($pool)-1];foreach($p['videos'] as &$vv){if(empty($vv['poster']))$vv['poster']=['src'=>$ph['src'],'id'=>$ph['id']];}unset($vv);}
    if(in_array($t,['cards','packages'],true)){foreach($p['items'] as &$it){if(!empty($it['image']['src'])&&in_array(self::c($it['image']['src']),['A','X','D'],true))unset($it['image']);}unset($it);}
    if($t==='video'){$vi=null;foreach($parts as $j=>$q)if(($q['type']??'')==='video'){$vi=$j;break;}if($vi!==null){$parts[$vi]['videos']=array_merge((array)$parts[$vi]['videos'],(array)$p['videos']);continue;}}
    $parts[]=$p;}
   $had_gal=in_array('gallery',array_column((array)($s['parts']??[]),'type'),true);$s['parts']=$parts;
   if($had_gal&&!$parts&&($s['layout']??'stack')!=='split')continue;
   if(empty($s['title'])&&empty($s['text'])&&!$parts&&($s['layout']??'stack')!=='split')continue;
   $out[]=$s;
  }
  // text-only sections become photo splits (alternating sides), and photo bands break up long pages
  $n=0;$res=[];$band_at=[1=>true,5=>true,9=>true];$i=0;
  foreach($out as $s){
   if(($s['layout']??'stack')==='stack'&&self::textish($s)&&($s['title']??'')!==''&&$pool&&count($pool)>2){$r=$take();$s['layout']='split';$s['media']=$r;$s['reverse']=($n++%2)===1;}
   $res[]=$s;$i++;
   if(isset($band_at[$i])&&count($pool)>=3&&($s['layout']??'')!=='cta'&&($r=$take(true))){$res[]=['layout'=>'band','media'=>$r,'eyebrow'=>'Real project','title'=>(string)($r['caption']?:''),'parts'=>[]];}
  }
  // text-only stacks: title on the left, intro + copy together on the right (no lopsided half-empty columns)
  foreach($res as &$s){if(($s['layout']??'stack')==='stack'&&self::textish($s)&&trim(wp_strip_all_tags((string)($s['text']??'')))!==''&&($s['title']??'')!==''){array_unshift($s['parts'],['type'=>'text','html'=>AGST_Spec::para($s['text'])]);$s['text']='';}}unset($s);
  if($views){$seen=[];$imgs=[];foreach($views as $v){$k=AGST_V2::key($v['src']);if(isset($seen[$k]))continue;$seen[$k]=1;$imgs[]=$v;}
   $cta=null;if($res&&($res[count($res)-1]['layout']??'')==='cta')$cta=array_pop($res);
   $res[]=['layout'=>'stack','eyebrow'=>'Reference','title'=>'Product views & drawings','parts'=>[['type'=>'drawings','images'=>$imgs]]];if($cta)$res[]=$cta;}
  // own-site media on https (upload URLs can come back as http:// and trigger mixed-content warnings)
  $host=(string)parse_url(home_url(),PHP_URL_HOST);if($host!==''){array_walk_recursive($res,function(&$v)use($host){if(is_string($v)&&strpos($v,'http://'.$host)!==false)$v=str_replace('http://'.$host,'https://'.$host,$v);});}
  return $res;
 }
}
// Admin: switch products to the v2 page (meta _agst_tpl_v2), staging pilot.
add_action('wp_ajax_agst_tpl_set',function(){if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);$on=!empty($_POST['on']);$o=[];foreach(array_map('intval',explode(',',(string)($_POST['ids']??''))) as $id){if(!$id||get_post_type($id)!=='product')continue;if($on)update_post_meta($id,'_agst_tpl_v2',1);else delete_post_meta($id,'_agst_tpl_v2');$o[]=$id;}wp_send_json_success($o);});
// Admin: point product photos at the original gallery images (same files/paths as the live site) instead of the
// generated copies in uploads/agst-media; the copies' alt/caption move to the agst_media_text map. Copies are kept.
add_action('wp_ajax_agst_media_use_originals',function(){if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);
 $map=json_decode((string)get_option('agst_media_text','{}'),true);if(!is_array($map))$map=[];$out=[];
 foreach(get_posts(['post_type'=>'product','post_status'=>'any','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_agst_media']) as $pid){$m=get_post_meta($pid,'_agst_media',true);if(!is_array($m)||empty($m['images']))continue;$new=[];$sw=0;
  foreach(array_map('intval',$m['images']) as $id){$src=(int)get_post_meta($id,'_agst_clean_of',true);if($src&&get_post_type($src)==='attachment'){$map[(string)$src]=['alt'=>(string)get_post_meta($id,'_wp_attachment_image_alt',true),'cap'=>(string)wp_get_attachment_caption($id)];$new[]=$src;$sw++;}else $new[]=$id;}
  $m['images']=array_values(array_unique($new));$m['originals']=1;update_post_meta($pid,'_agst_media',$m);$out[$pid]=$sw;}
 update_option('agst_media_text',wp_json_encode($map),false);wp_send_json_success(['products'=>count($out),'swapped'=>array_sum($out),'texts'=>count($map)]);});
// Read-only inventory for the live release (what staging changed).
add_action('wp_ajax_agst_inventory',function(){if(!current_user_can('manage_woocommerce')||!wp_verify_nonce((string)($_POST['nonce']??''),'wp_rest'))wp_send_json_error('forbidden',403);global $wpdb;$o=[];
 $o['status']=$wpdb->get_results("SELECT post_type,post_status,COUNT(*) n,MIN(ID) mn,MAX(ID) mx FROM {$wpdb->posts} WHERE post_type IN('product','product_variation') GROUP BY post_type,post_status",ARRAY_A);
 $o['first_staging_product']=$wpdb->get_row("SELECT ID,post_date FROM {$wpdb->posts} WHERE post_type IN('product','product_variation') AND post_date>='2026-09-15' ORDER BY ID ASC LIMIT 1",ARRAY_A);
 $o['last_before']=$wpdb->get_results("SELECT post_type,MAX(ID) mx,MAX(post_date) d FROM {$wpdb->posts} WHERE post_date<'2026-09-15' GROUP BY post_type ORDER BY mx DESC LIMIT 12",ARRAY_A);
 $o['posts_after_by_type']=$wpdb->get_results("SELECT post_type,post_status,COUNT(*) n,MIN(post_date) d0 FROM {$wpdb->posts} WHERE post_date>='2026-09-15' GROUP BY post_type,post_status",ARRAY_A);
 $o['meta_keys']=$wpdb->get_results("SELECT m.meta_key k,COUNT(*) n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type IN('product','product_variation') AND (m.meta_key LIKE '\\_agst%' OR m.meta_key LIKE '\\_yoast%' OR m.meta_key LIKE '\\_elementor%' OR m.meta_key LIKE '\\_agx%') GROUP BY m.meta_key ORDER BY n DESC",ARRAY_A);
 $o['options']=$wpdb->get_results("SELECT option_name n,LENGTH(option_value) len,autoload FROM {$wpdb->options} WHERE option_name LIKE 'agst%' ORDER BY option_name",ARRAY_A);
 $o['terms']=$wpdb->get_results("SELECT t.term_id,t.slug,t.name,tt.parent,tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy='product_cat' ORDER BY t.term_id",ARRAY_A);
 $o['attr_terms_after']=$wpdb->get_results("SELECT tt.taxonomy,t.term_id,t.slug FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy LIKE 'pa\\_%' AND t.term_id>600 ORDER BY t.term_id",ARRAY_A);
 $o['termmeta']=$wpdb->get_results("SELECT meta_key,COUNT(*) n FROM {$wpdb->termmeta} GROUP BY meta_key",ARRAY_A);
 $t=$wpdb->prefix.'redirection_groups';if($wpdb->get_var("SHOW TABLES LIKE '$t'")===$t){$o['redir_groups']=$wpdb->get_results("SELECT g.id,g.name,COUNT(i.id) n FROM $t g LEFT JOIN {$wpdb->prefix}redirection_items i ON i.group_id=g.id GROUP BY g.id",ARRAY_A);$o['redir_max']=$wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}redirection_items");$o['redir_cols']=$wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}redirection_items");}
 $o['wpcode']=$wpdb->get_results("SELECT ID,post_type,post_title,post_status,post_modified FROM {$wpdb->posts} WHERE ID IN(39281,39726,48849,30518)",ARRAY_A);
 $o['att_after']=$wpdb->get_row("SELECT COUNT(*) n,MIN(ID) mn,MAX(ID) mx FROM {$wpdb->posts} WHERE post_type='attachment' AND post_date>='2026-09-15'",ARRAY_A);
 $o['modified_after']=$wpdb->get_results("SELECT post_type,COUNT(*) n FROM {$wpdb->posts} WHERE post_modified>='2026-09-15' AND post_date<'2026-09-15' GROUP BY post_type",ARRAY_A);
 wp_send_json_success($o);});
