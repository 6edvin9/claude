<?php
/**
 * Plugin Name: Aluglobus Staging Catalog Test
 * Description: Reviewed draft-PDF catalog test with product snapshots and rollback. Staging only.
 * Version: 0.6.0
 * Requires PHP: 7.4
 * Requires at least: 6.2
 */
if (!defined('ABSPATH')) { exit; }
final class AGST_Catalog {
 const VERSION='0.3.1';
 const ASSET_VERSION = '2.3.6';
 const META=['_elementor_data','_elementor_edit_mode','_elementor_template_type','_elementor_page_settings','_yoast_wpseo_title','_yoast_wpseo_metadesc','_agst_key','_agst_managed','_agst_media_inventory','_agst_version'];
 static function boot(){
  add_action('admin_menu',function(){add_submenu_page('woocommerce','Aluglobus Catalog Test','Aluglobus Catalog Test','manage_woocommerce','agst-catalog',[__CLASS__,'admin']);});
  add_action('wp_ajax_agst_catalog',[__CLASS__,'ajax']);
  add_action('wp_enqueue_scripts',function(){if(function_exists('is_product')&&is_product()&&get_post_meta(get_queried_object_id(),'_agst_managed',true)){wp_enqueue_style('agst-catalog',plugins_url('catalog.css',__FILE__),[],self::VERSION);}});
 }
 static function manifest(){static $m;if(!$m){$m=json_decode(file_get_contents(__DIR__.'/manifest.json'),true);if(!is_array($m))throw new RuntimeException('Manifest is unreadable.');if(class_exists('AGST_Fixes'))$m=array_map(['AGST_Fixes','prepare_row'],$m);}return $m;}
 static function row($key){foreach(self::manifest() as $r)if($r['key']===$key)return $r;throw new RuntimeException('Unknown product key.');}
 static function guard(){
  if(!current_user_can('manage_woocommerce')||!current_user_can('edit_products'))throw new RuntimeException('WooCommerce product-management access is required.');
  if(!function_exists('wc_get_product'))throw new RuntimeException('WooCommerce must be active.');
  if(!in_array(wp_get_environment_type(),['staging','local','development'],true))throw new RuntimeException('Set WP_ENVIRONMENT_TYPE to staging on this staging installation.');
  foreach([home_url(),site_url()] as $url){$host=strtolower((string)parse_url($url,PHP_URL_HOST));if(in_array($host,['aluglobusfence.com','www.aluglobusfence.com','globusgates.com','www.globusgates.com','aluglobusaluminum.com','www.aluglobusaluminum.com'],true))throw new RuntimeException('Live website host blocked.');}
  if((string)get_option('blog_public')!=='0')throw new RuntimeException('Enable Settings > Reading > Discourage search engines on staging.');
  if(get_woocommerce_currency()!=='USD')throw new RuntimeException('The source prices require USD.');
 }
 static function norm($s){return trim(html_entity_decode($s,ENT_QUOTES|ENT_HTML5,'UTF-8'));}
 static function prior($key){$ids=get_posts(['post_type'=>'product','post_status'=>'any','fields'=>'ids','numberposts'=>2,'meta_key'=>'_agst_key','meta_value'=>$key]);if(count($ids)>1)throw new RuntimeException('Duplicate import keys found.');return $ids?(int)$ids[0]:0;}
 static function preflight($r){
  if(!in_array($r['action'],['create','update'],true))throw new RuntimeException('This entry is held or an alias and cannot be imported.');
  if(self::prior($r['key'])){if(get_post_meta(self::prior($r['key']),'_agst_version',true)!==self::VERSION)return 'upgrade';return 'already';}
  AGST_Variations::preflight($r);
  if(get_option('agst_backup_'.$r['key'],false)!==false)throw new RuntimeException('An earlier snapshot exists. Restore it before retrying.');
  if($r['action']==='create'){
   if(get_page_by_path($r['slug'],OBJECT,'product')||wc_get_product_id_by_sku($r['sku']))throw new RuntimeException('Proposed slug or SKU already exists; resolve the collision.');return 'ready';
  }
  $p=wc_get_product($r['target_id']);
  if(!$p||self::norm($p->get_name())!==self::norm($r['expected_name'])||$p->get_type()!==$r['expected_type']||$p->get_sku()!==$r['expected_sku'])throw new RuntimeException('Product ID, name, type or SKU differs from the reviewed export.');
  $q=wc_get_product($r['price_target_id']);if(!$q)throw new RuntimeException('Price target missing.');
  if($r['price_target_id']!==$r['target_id']){
   if($q->get_type()!=='variation'||$q->get_parent_id()!==$p->get_id()||!in_array('black',array_map('strtolower',array_values($q->get_attributes())),true))throw new RuntimeException('Expected Black variation does not match.');
  }
  if((string)$q->get_sale_price()!==''||$q->get_date_on_sale_from()||$q->get_date_on_sale_to())throw new RuntimeException('Sale pricing or schedule needs separate review.');
  if((string)$q->get_regular_price()==='' ? $r['old_price']!=='' : ($r['old_price']===''||(float)$q->get_regular_price()!==(float)$r['old_price']))throw new RuntimeException('Regular price has changed since the reviewed export.');
  return 'ready';
 }
 static function snapshot($id,$price_id){
  $p=wc_get_product($id);$meta=[];foreach(self::META as $k)$meta[$k]=['exists'=>metadata_exists('post',$id,$k),'value'=>get_post_meta($id,$k,true)];
  return ['new'=>false,'id'=>$id,'price_id'=>$price_id,'price'=>wc_get_product($price_id)->get_regular_price(),'name'=>$p->get_name(),'description'=>$p->get_description(),'short'=>$p->get_short_description(),'status'=>$p->get_status(),'slug'=>$p->get_slug(),'categories'=>$p->get_category_ids(),'image'=>$p->get_image_id(),'gallery'=>$p->get_gallery_image_ids(),'meta'=>$meta];
 }
 static function local_url($url){
  $url=html_entity_decode(str_replace('\\/','/',$url),ENT_QUOTES|ENT_HTML5,'UTF-8');$parts=wp_parse_url($url);if(!$parts)return '';
  if(empty($parts['host']))return isset($url[0])&&$url[0]==='/'&&substr($url,0,2)!=='//'? $url:'';
  $host=strtolower($parts['host']);$own=strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST));
  if(in_array($host,[$own,'aluglobusfence.com','www.aluglobusfence.com'],true)){
   $path=$parts['path']??'/';$prefix=rtrim((string)wp_parse_url(home_url(),PHP_URL_PATH),'/');
   if($host!==$own&&$prefix&&strpos($path,$prefix.'/')!==0)$path=$prefix.$path;
   return $path.(isset($parts['query'])?'?'.$parts['query']:'').(isset($parts['fragment'])?'#'.$parts['fragment']:'');
  }return preg_match('~^https?://~i',$url)?$url:'';
 }
 static function media($id){
  $items=[];
  $add=function($url,$alt='')use(&$items){$url=self::local_url($url);if(!$url)return;$path=(string)parse_url($url,PHP_URL_PATH);$type='';
   if(preg_match('~\.(jpe?g|png|gif|webp|avif|svg)$~i',$path))$type='image';
   elseif(preg_match('~\.(mp4|webm|mov|m4v|ogv)$~i',$path))$type='video';
   elseif(preg_match('~https?://(?:www\.|player\.)?(?:youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com)/~i',$url))$type='embed';
   if($type){if(!isset($items[$url]))$items[$url]=['url'=>$url,'type'=>$type,'alt'=>$alt];elseif($alt&&!$items[$url]['alt'])$items[$url]['alt']=$alt;}
  };
  $attach=function($aid)use($add){if($aid&&get_post_type($aid)==='attachment'){$u=wp_get_attachment_url($aid);if($u)$add($u,get_post_meta($aid,'_wp_attachment_image_alt',true));}};
  $walk=null;$walk=function($v,$depth=0)use(&$walk,$add,$attach,&$items){if($depth>35)return;
   if(is_array($v)){foreach($v as $k=>$w){if(in_array((string)$k,['id','image_id','attachment_id'],true)&&is_numeric($w))$attach((int)$w);$walk($w,$depth+1);}return;}
   if(!is_string($v))return;$v=str_replace('\\/','/',$v);$j=json_decode($v,true);if(is_array($j)){$walk($j,$depth+1);return;}
   $s=maybe_unserialize($v);if(is_array($s)){$walk($s,$depth+1);return;}
   if(preg_match_all('~<img\b[^>]*>~i',$v,$tags)){foreach($tags[0] as $tag){if(preg_match('~\bsrc=["\x27]([^"\x27]+)~i',$tag,$src)){preg_match('~\balt=["\x27]([^"\x27]*)~i',$tag,$alt);$add($src[1],html_entity_decode($alt[1]??'',ENT_QUOTES));}}}
   if(preg_match_all('~(?:https?://|/wp-content/)[^\s<>"\x27\\\\]+~i',$v,$m)){foreach($m[0] as $u)$add(rtrim($u,');,'));}
   if(preg_match_all('~<iframe\b[^>]*src=["\x27]([^"\x27]+)~i',$v,$frames))foreach($frames[1] as $u){$u=self::local_url($u);if($u&&!isset($items[$u]))$items[$u]=['url'=>$u,'type'=>'link','alt'=>'Original embedded media'];}
  };
  $p=wc_get_product($id);$attach($p->get_image_id());foreach($p->get_gallery_image_ids() as $aid)$attach($aid);
  $walk($p->get_description());$walk($p->get_short_description());foreach(get_post_meta($id) as $k=>$v){if(strpos($k,'_agst_')===0||preg_match('/oembed|element_cache|elementor_css/',$k))continue;$walk($v);}
  return array_values($items);
 }
 static function media_html($items,$title){
  $images='';$videos='';foreach($items as $m){$url=esc_url($m['url']);$alt=esc_attr($m['alt']?:$title);
   if($m['type']==='image'){$images.='<figure><a href="'.$url.'"><img src="'.$url.'" alt="'.$alt.'" loading="lazy" decoding="async"></a></figure>';continue;}
   if($m['type']==='video'){$videos.='<video controls playsinline preload="none" src="'.$url.'"></video>';continue;}
   $embed='';if(preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|watch\?v=))([a-zA-Z0-9_-]{11})~',$m['url'],$v))$embed='https://www.youtube-nocookie.com/embed/'.$v[1];
   elseif(preg_match('~vimeo\.com/(?:video/)?(\d+)~',$m['url'],$v)){$embed='https://player.vimeo.com/video/'.$v[1];parse_str((string)parse_url($m['url'],PHP_URL_QUERY),$q);if(isset($q['h']))$embed.='?h='.rawurlencode($q['h']);}
   if($embed)$videos.='<iframe loading="lazy" src="'.esc_url($embed).'" title="'.esc_attr($title.' video').'" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>';
   $videos.='<p><a href="'.$url.'">Open original video or embedded media</a></p>';
  }
  return ($images?'<div class="agst-gallery'.(substr_count($images,'<figure>')===1?' agst-gallery-single':'').'">'.$images.'</div>':'').($videos?'<div class="agst-videos">'.$videos.'</div>':'').(!$images&&!$videos?'<p>Product imagery is awaiting confirmation.</p>':'');
 }
 static function assets($r){
  require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';$ids=[];
  foreach($r['asset_files'] as $name){$src=__DIR__.'/assets/'.basename($name);if(!is_file($src))throw new RuntimeException('Missing packaged image.');$hash=hash_file('sha256',$src);$prior=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'fields'=>'ids','meta_key'=>'_agst_asset_hash','meta_value'=>$hash]);
   if($prior){$ids[]=(int)$prior[0];continue;}$tmp=wp_tempnam($name);if(!$tmp||!copy($src,$tmp))throw new RuntimeException('Cannot prepare product image.');$id=media_handle_sideload(['name'=>$name,'tmp_name'=>$tmp],0,$r['title']);if(is_wp_error($id)){@unlink($tmp);throw new RuntimeException($id->get_error_message());}update_post_meta($id,'_agst_asset_hash',$hash);update_post_meta($id,'_wp_attachment_image_alt',wp_slash($r['title']));$ids[]=$id;
  }return $ids;
 }
 static function category($path){$parent=0;foreach(explode('>',$path) as $part){$part=trim($part);$term=term_exists($part,'product_cat',$parent);if(!$term||is_wp_error($term))throw new RuntimeException('Existing category not found: '.$path.'. Import stopped; no category created.');$parent=(int)(is_array($term)?$term['term_id']:$term);}return $parent;}
 static function clear($id){delete_post_meta($id,'_elementor_css');delete_post_meta($id,'_elementor_element_cache');wc_delete_product_transients($id);clean_post_cache($id);if(class_exists('Elementor\\Core\\Files\\CSS\\Post')){try{$css=new \Elementor\Core\Files\CSS\Post($id);$css->delete();}catch(Throwable $e){}}}
 static function apply($r){
  $state=self::preflight($r);if($state==='upgrade')return AGST_Preservation::upgrade($r,self::prior($r['key']));if($state==='already')return ['id'=>self::prior($r['key']),'message'=>'Already applied; skipped.'];$key='agst_backup_'.$r['key'];$isnew=$r['action']==='create';$id=0;
  if(!$isnew){$id=$r['target_id'];$identity=AGST_Preservation::identity($id);$snapshot=self::snapshot($id,$r['price_target_id']);$snapshot['identity']=$identity;if(!add_option($key,$snapshot,'','no'))throw new RuntimeException('Could not save rollback snapshot.');}
  else{if(!add_option($key,['new'=>true,'id'=>0],'','no'))throw new RuntimeException('Could not reserve rollback record.');}
  try{
   $p=$isnew?new WC_Product_Simple():wc_get_product($id);$media=$isnew?[]:self::media($id);
   if($isnew){$p->set_status('draft');$p->set_catalog_visibility('hidden');$p->set_stock_status('instock');$p->set_slug($r['slug']);$p->set_sku($r['sku']);$p->set_category_ids(array_map([__CLASS__,'category'],array_map('trim',explode(',',$r['categories']))));}
   if($isnew)$p->set_name($r['title']);$id=$p->save();if(!$id)throw new RuntimeException('Product save failed.');if($isnew)update_option($key,['new'=>true,'id'=>$id],false);
   if($isnew||!$p->get_image_id()){$assets=self::assets($r);if($assets){$p->set_image_id($assets[0]);$p->set_gallery_image_ids(array_values(array_unique(array_merge($p->get_gallery_image_ids(),array_slice($assets,1)))));$p->save();foreach($assets as $aid){$u=self::local_url(wp_get_attachment_url($aid));$found=false;foreach($media as $m)if($m['url']===$u)$found=true;if(!$found)$media[]=['url'=>$u,'type'=>'image','alt'=>$r['title']];}}}
   $body=str_replace('{{MEDIA}}',self::media_html($media,$r['title']),$r['body']);if($isnew){$p->set_description($body);$p->set_short_description($r['short_html']);}
   if($r['price']!==null&&($isnew||$r['price_target_id']===$id)){$p->set_regular_price($r['price']);$p->set_price($r['price']);}$p->save();
   if(!$isnew&&$r['price_target_id']!==$id&&$r['price']!==null){$q=wc_get_product($r['price_target_id']);$q->set_regular_price($r['price']);$q->set_price($r['price']);$q->save();WC_Product_Variable::sync($id);}
   if($isnew){$data=[['id'=>'agstwrap','elType'=>'container','settings'=>['content_width'=>'full'],'elements'=>[['id'=>'agsthtml','elType'=>'widget','widgetType'=>'html','settings'=>['html'=>$body],'elements'=>[]]]]];
   update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data)));update_post_meta($id,'_elementor_edit_mode','builder');update_post_meta($id,'_elementor_template_type','wp-post');
   $settings=get_post_meta($id,'_elementor_page_settings',true);if(is_array($settings)){unset($settings['custom_css']);update_post_meta($id,'_elementor_page_settings',wp_slash($settings));}
   update_post_meta($id,'_yoast_wpseo_title',wp_slash($r['seo_title']));update_post_meta($id,'_yoast_wpseo_metadesc',wp_slash($r['seo_description']));}update_post_meta($id,'_agst_media_inventory',wp_slash($media));AGST_Variations::apply($r,$id);update_post_meta($id,'_agst_version',self::VERSION);update_post_meta($id,'_agst_managed',1);update_post_meta($id,'_agst_key',$r['key']);self::clear($id);if(!$isnew)AGST_Preservation::verify($identity);
   return ['id'=>$id,'message'=>$isnew?'Created hidden draft.':'Updated price/options; original content and SEO preserved. URL verified.','media_count'=>count($media)];
  }catch(Throwable $e){try{self::restore($r['key']);}catch(Throwable $restore){throw new RuntimeException($e->getMessage().' Automatic rollback also failed: '.$restore->getMessage().' Keep the backup and restore staging from your site backup.');}throw new RuntimeException($e->getMessage().' Changes rolled back.');}
 }
 static function restore($key){
  $option='agst_backup_'.$key;$b=get_option($option,false);if(!$b)throw new RuntimeException('No rollback record.');$id=(int)$b['id'];AGST_Variations::restore($b);
  if($b['new']){if($id){$result=wp_trash_post($id);if(!$result)throw new RuntimeException('Cannot trash the new draft.');delete_post_meta($id,'_agst_key');delete_post_meta($id,'_agst_managed');}}
  else{$p=wc_get_product($id);if(!$p)throw new RuntimeException('Original product missing.');$p->set_name($b['name']);$p->set_description($b['description']);$p->set_short_description($b['short']);$p->set_status($b['status']);$p->set_slug($b['slug']);$p->set_category_ids($b['categories']);$p->set_image_id($b['image']);$p->set_gallery_image_ids($b['gallery']);$p->save();$q=wc_get_product($b['price_id']);if(!$q)throw new RuntimeException('Original price target missing.');$q->set_regular_price($b['price']);$q->set_price($b['price']);$q->save();if($p->get_type()==='variable')WC_Product_Variable::sync($id);foreach($b['meta'] as $k=>$m){if($m['exists'])update_post_meta($id,$k,wp_slash($m['value']));else delete_post_meta($id,$k);}self::clear($id);}
  delete_option($option);return ['id'=>$id,'message'=>$b['new']?'New draft moved to trash.':'Original fields restored.'];
 }
 static function ajax(){
  check_ajax_referer('agst-catalog','nonce');$locked=false;
  try{self::guard();$key=sanitize_text_field(wp_unslash($_POST['key']??''));$r=self::row($key);$mode=sanitize_key($_POST['mode']??'');if(!in_array($mode,['apply','restore'],true))throw new RuntimeException('Unknown operation.');
   if(!add_option('agst_import_lock',time(),'','no'))throw new RuntimeException('Another import is running or a stale lock needs inspection.');$locked=true;
   $result=$mode==='restore'?self::restore($key):self::apply($r);if(!empty($result['id']))$result['preview']=get_preview_post_link($result['id']);
  }catch(Throwable $e){if($locked)delete_option('agst_import_lock');wp_send_json_error(['message'=>$e->getMessage()],400);return;}
  if($locked)delete_option('agst_import_lock');wp_send_json_success($result);
 }
 static function admin(){
  if(!current_user_can('manage_woocommerce'))return;$error='';try{self::guard();}catch(Throwable $e){$error=$e->getMessage();}
  echo '<div class="wrap"><h1>Aluglobus Catalog Test 0.3.1</h1><p>Draft PDF: 90 new parent drafts, 64 existing updates, 60 holds, 32 color rows grouped under 18 variable families, 7 aliases. No automatic import. Product pages use the unified clean layout with the standard WooCommerce cart.</p>';
  if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="agst_layout">';wp_nonce_field('agst-layout');echo '<p><label><input type="checkbox" name="enabled" value="1" '.checked(get_option('agst_full_shop_layout',false),true,false).'> Use the new layout on all staging product pages (preserves unmatched product data)</label> <button class="button" '.($error?'disabled':'').'>Save layout scope</button></p></form><p><strong>Upgrade existing tests:</strong> Select an applied parent and Apply selected to upgrade it in place. Existing IDs and URLs stay intact. Older standalone color drafts require separate review. Restore selected returns the original pre-test state.</p>';
  echo '<p>Take a staging backup first. Test a few rows and verify every image/video against the original. Restoring overwrites edits made to imported fields after the import.</p><input id="agst-search" type="search" placeholder="Filter title, key or action"> <button id="agst-select" class="button">Select visible ready rows</button> <button id="agst-clear" class="button">Clear selection</button> <button id="agst-apply" class="button button-primary" '.($error?'disabled':'').'>Apply selected</button> <button id="agst-restore" class="button" '.($error?'disabled':'').'>Restore selected</button><p id="agst-status" role="status"></p><table class="widefat striped"><thead><tr><th>Select</th><th>Source</th><th>Action / ID</th><th>Product</th><th>Regular price USD</th><th>Notes / Result</th></tr></thead><tbody>';
  foreach(self::manifest() as $r){$backup=get_option('agst_backup_'.$r['key'],false)!==false;$ready=in_array($r['action'],['create','update'],true);echo '<tr data-key="'.esc_attr($r['key']).'" data-ready="'.($ready&&!$backup?'1':'0').'"><td><input type="checkbox" '.(!$ready&&!$backup?'disabled':'').'></td><td>'.esc_html($r['key']).'</td><td>'.esc_html($r['action'].' '.($r['target_id']?:'')).'</td><td>'.esc_html($r['title']).'</td><td>'.esc_html(($r['old_price']?:'unset').' → '.($r['price']??'unconfirmed')).'</td><td class="agst-result">'.esc_html(($backup?'Applied or interrupted; rollback record exists. ':'').$r['reason'].' '.implode(' ',$r['notes'])).'</td></tr>';}
  echo '</tbody></table></div>';wp_enqueue_script('agst-admin',plugins_url('admin.js',__FILE__),[],self::VERSION,true);wp_localize_script('agst-admin','AGST',['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('agst-catalog')]);
 }
}
require_once __DIR__.'/preservation.php';
require_once __DIR__.'/variations.php';
require_once __DIR__.'/storefront.php';
AGST_Catalog::boot();

require_once __DIR__.'/qa.php';
