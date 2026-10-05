<?php
if (!defined('ABSPATH')) exit;
final class AGST_Preservation {
 static function identity($id){
  $p=wc_get_product($id);$url=get_permalink($id);
  if(!$p||!$url)throw new RuntimeException('Cannot capture product URL.');
  $cats=$p->get_category_ids();sort($cats);
  return ['id'=>$id,'slug'=>$p->get_slug(),'url'=>$url,'categories'=>$cats,'primary'=>get_post_meta($id,'_yoast_wpseo_primary_product_cat',true),'canonical'=>get_post_meta($id,'_yoast_wpseo_canonical',true)];
 }
 static function verify($before){
  $after=self::identity($before['id']);
  if($before!==$after)throw new RuntimeException('URL, slug, category or canonical changed. Update rejected.');
 }
 static function restore_fields($b){
  $p=wc_get_product($b['id']);
  if(!$p)throw new RuntimeException('Product missing during content recovery.');
  $p->set_name($b['name']);$p->set_description($b['description']);$p->set_short_description($b['short']);$p->save();
  foreach($b['meta'] as $key=>$m){if(strpos($key,'_elementor_')!==0&&strpos($key,'_yoast_')!==0)continue;if($m['exists'])update_post_meta($b['id'],$key,wp_slash($m['value']));else delete_post_meta($b['id'],$key);}
 }
 static function upgrade($r,$id){
  $key='agst_backup_'.$r['key'];$b=get_option($key,false);
  if(!$b||empty($b['id'])||(int)$b['id']!==$id)throw new RuntimeException('Original rollback record missing. Cannot safely upgrade.');
  $identity=self::identity($id);$current=AGST_Catalog::snapshot($id,$id);$added=false;
  if(!empty($r['variants'])&&wc_get_product($id)->get_type()==='simple'){
   AGST_Variations::preflight($r);
  }
  try{
   if(!$b['new'])self::restore_fields($b);
   if(!empty($r['variants'])&&wc_get_product($id)->get_type()==='simple'){$added=true;AGST_Variations::apply($r,$id);}
   update_post_meta($id,'_agst_version',AGST_Catalog::VERSION);AGST_Catalog::clear($id);self::verify($identity);
   return ['id'=>$id,'message'=>'Upgraded in place; URL verified. '.(!$b['new']?'Original content and SEO fields recovered.':'Parent ID retained.')];
  }catch(Throwable $e){
   if($added)AGST_Variations::restore(get_option($key));
   self::restore_fields($current);foreach($current['meta'] as $k=>$m){if($m['exists'])update_post_meta($id,$k,wp_slash($m['value']));else delete_post_meta($id,$k);}
   $p=wc_get_product($id);$p->set_regular_price($current['price']);$p->set_price($current['price']);$p->save();update_option($key,$b,false);AGST_Catalog::clear($id);throw $e;
  }
 }
 static function layout_classes($value){
  $out=[];foreach(preg_split('/\s+/',strtolower($value)) as $c){
   if(preg_match('/(?:^|[-_])(?:intro-grid|hero-grid|split|two|two-col|cols-2|benefits-grid)$/',$c))$out[]='agx-l-split';
   elseif(preg_match('/(?:^|[-_])(?:stat-row|stats|stats-grid|facts|facts-grid)$/',$c))$out[]='agx-l-stats';
   elseif(preg_match('/(?:^|[-_])(?:btn-row|btns|buttons|actions|cta-actions)$/',$c))$out[]='agx-l-actions';
   elseif(preg_match('/(?:^|[-_])(?:spec-row|specrow|fire-row)$/',$c))$out[]='agx-l-spec-row';
   elseif(preg_match('/(?:^|[-_])(?:spec-grid|specs-grid|specs|spec-table)$/',$c))$out[]='agx-l-specs';
   elseif(preg_match('/(?:^|[-_])(?:grid|cards|steps|grid-wrapper)$/',$c))$out[]='agx-l-grid';
   if(preg_match('/(?:^|[-_])(?:shell|container|wrap)$/',$c))$out[]='agx-l-shell';
   if(preg_match('/(?:^|[-_])(?:section)$/',$c))$out[]='agx-l-section';
   if(preg_match('/(?:^|[-_])(?:card|stat|step|grid-item|gitem|gallery-item|feature-item|finish-item|part|app|benefit)$/',$c))$out[]='agx-l-card';
   if(preg_match('/(?:^|[-_])(?:intro-media|hero-media|hero-visual|hero-art|media)$/',$c))$out[]='agx-l-media';
   if(preg_match('/(?:^|[-_])(?:copy|body)$/',$c))$out[]='agx-l-copy';
   if(preg_match('/(?:^|[-_])(?:eyebrow|kicker|label)$/',$c))$out[]='agx-l-eyebrow';
   if(preg_match('/(?:^|[-_])(?:btn|button)$/',$c))$out[]='agx-l-button';
   if(preg_match('/(?:^|[-_])(?:note|callout)$/',$c))$out[]='agx-l-callout';
   if(preg_match('/(?:lightbox|modal|viewer)/',$c)&&!preg_match('/(?:link|trigger|button)/',$c))$out[]='agx-l-old-viewer';
  }return implode(' ',array_unique($out));
 }
 static function clean($html){
  $html=str_replace('\\n',"\n",(string)$html);
  $html=preg_replace('~<(script|style|noscript)\b[^>]*>.*?</\1>~is','',(string)$html);
  $html=preg_replace_callback('~\sclass\s*=\s*(["\x27])(.*?)\1~is',function($m){$classes=self::layout_classes($m[2]);return $classes?' class="'.$classes.'"':'';},$html);
  $html=preg_replace('~\sstyle\s*=\s*(["\x27]).*?\1~is','',$html);
  $html=preg_replace('~<(\/?)h1\b~i','<$1h2',$html);
  $html=preg_replace_callback('~\b(href|src)=(["\x27])(.*?)\2~is',function($m){return $m[1].'='.$m[2].esc_url(AGST_Catalog::local_url($m[3])?:$m[3]).$m[2];},$html);
  return wp_kses_post($html);
 }
 // Build presentation markup only. Stored Elementor, copy and SEO fields are never written.
 static function present($html){
  if(!$html)return '';
  if(!class_exists('DOMDocument'))return '<section class="agx-editorial agx-section"><div class="agx-editorial-body">'.$html.'</div></section>';
  $doc=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
  $doc->loadHTML('<?xml encoding="UTF-8"><div id="agst-content-root">'.$html.'</div>',LIBXML_HTML_NODEFDTD|LIBXML_HTML_NOIMPLIED);
  libxml_clear_errors();libxml_use_internal_errors($previous);$xp=new DOMXPath($doc);$root=$doc->getElementById('agst-content-root');if(!$root)return '';
  // Old scripted viewers/buttons cannot run after their scripts are removed. Media is available through the shared viewer and video section.
  foreach(iterator_to_array($xp->query('.//button',$root)) as $el){foreach(iterator_to_array($el->getElementsByTagName('img')) as $img){$link=$doc->createElement('a');$link->setAttribute('href','#agx-films');$link->setAttribute('aria-label','Watch product videos');$el->parentNode->insertBefore($link,$el);$link->appendChild($img);}$el->parentNode->removeChild($el);}
  foreach(iterator_to_array($xp->query('.//dialog|.//video|.//iframe',$root)) as $el){if($el->nodeName!=='dialog'){$link=$doc->createElement('a','Watch product videos ↗');$link->setAttribute('href','#agx-films');$link->setAttribute('class','agx-text-link');$el->parentNode->insertBefore($link,$el);}$el->parentNode->removeChild($el);}
  foreach(iterator_to_array($xp->query('.//img',$root)) as $img){if(!$img->getAttribute('src')){$img->parentNode->removeChild($img);continue;}$img->setAttribute('loading','lazy');$img->setAttribute('decoding','async');$img->removeAttribute('width');$img->removeAttribute('height');}
  // Map meaningful old sections to a consistent editorial component without flattening their text.
  $sections=iterator_to_array($xp->query('.//section[not(ancestor::section)]',$root));
  if(!$sections){$section=$doc->createElement('section');while($root->firstChild)$section->appendChild($root->firstChild);$root->appendChild($section);$sections=[$section];}
  foreach($sections as $section){
   $section->setAttribute('class',trim($section->getAttribute('class').' agx-editorial agx-section'));
   $body=$doc->createElement('div');$body->setAttribute('class','agx-editorial-body');while($section->firstChild)$body->appendChild($section->firstChild);$section->appendChild($body);
   // Consecutive cards form a responsive grid. Do not reorder text or split headings from their copy.
   foreach(iterator_to_array($xp->query('.//article|.//figure',$body)) as $card)$card->setAttribute('class',trim($card->getAttribute('class').' agx-editorial-card'));
   $cards=iterator_to_array($xp->query('.//article|.//figure',$body));
   foreach($cards as $card){if(!$card->parentNode||preg_match('/agx-(?:editorial-grid|l-grid|l-split|l-stats)/',$card->parentNode->getAttribute('class')))continue;
    $next=$card->nextSibling;while($next&&$next->nodeType===XML_TEXT_NODE&&trim($next->textContent)==='')$next=$next->nextSibling;
    if(!$next||$next->nodeType!==XML_ELEMENT_NODE||!in_array($next->nodeName,['article','figure'],true))continue;
    $grid=$doc->createElement('div');$grid->setAttribute('class','agx-editorial-grid');$card->parentNode->insertBefore($grid,$card);$node=$card;
    while($node){$after=$node->nextSibling;if(($node->nodeType===XML_TEXT_NODE&&trim($node->textContent)==='')||($node->nodeType===XML_ELEMENT_NODE&&in_array($node->nodeName,['article','figure'],true)))$grid->appendChild($node);else break;$node=$after;}
   }
   foreach(iterator_to_array($xp->query('.//div[not(@class)]',$body)) as $group){
    $children=[];foreach($group->childNodes as $child)if($child->nodeType===XML_ELEMENT_NODE)$children[]=$child;
    if(count($children)<2)continue;$valid=true;foreach($children as $child)if($child->nodeName!=='div'||(!$child->getElementsByTagName('small')->length&&!$child->getElementsByTagName('ul')->length&&!$child->getElementsByTagName('span')->length))$valid=false;
    if($valid){$group->setAttribute('class','agx-editorial-grid');foreach($children as $child)$child->setAttribute('class','agx-editorial-card');}
   }
   foreach(iterator_to_array($xp->query('.//table',$body)) as $table){$wrap=$doc->createElement('div');$wrap->setAttribute('class','agx-table-scroll');$wrap->setAttribute('tabindex','0');$wrap->setAttribute('role','region');$wrap->setAttribute('aria-label','Product specifications');$table->parentNode->insertBefore($wrap,$table);$wrap->appendChild($table);}
  }
  $out='';foreach($root->childNodes as $child)$out.=$doc->saveHTML($child);return $out;
 }
 static function content($p){
  $key=get_post_meta($p->get_id(),'_agst_key',true);$b=$key?get_option('agst_backup_'.$key,false):false;
  if($b&&!empty($b['new']))return '';
  $use_backup=$b&&version_compare((string)get_post_meta($p->get_id(),'_agst_version',true),'0.3.1','<');
  $long=$use_backup?$b['description']:$p->get_description();$short=$use_backup?$b['short']:$p->get_short_description();
  $data=$use_backup?($b['meta']['_elementor_data']['value']??''):get_post_meta($p->get_id(),'_elementor_data',true);
  $chunks=[$short,$long];$tree=is_array($data)?$data:json_decode($data,true);
  $walk=null;$walk=function($nodes)use(&$walk,&$chunks){foreach((array)$nodes as $node){if(!is_array($node))continue;$s=$node['settings']??[];$type=$node['widgetType']??'';
   if($type==='html')$chunks[]=$s['html']??'';
   if($type==='text-editor')$chunks[]=$s['editor']??'';
   if($type==='nested-accordion'){foreach(($node['elements']??[]) as $i=>$child){$item=$s['items'][$i]??[];if(!empty($item['item_title']))$chunks[]='<h3>'.esc_html($item['item_title']).'</h3>';$walk([$child]);}continue;}
   if($type==='heading'&&!empty($s['title']))$chunks[]='<h2>'.esc_html($s['title']).'</h2>';
   if(in_array($type,['accordion','toggle','tabs'],true))foreach(($s['tabs']??[]) as $t)$chunks[]='<h3>'.esc_html($t['tab_title']??'').'</h3>'.($t['tab_content']??'');
   if(in_array($type,['icon-box','image-box'],true))$chunks[]='<h3>'.esc_html($s['title_text']??'').'</h3><p>'.($s['description_text']??'').'</p>';
   if($type==='icon-list')foreach(($s['icon_list']??[]) as $item)$chunks[]='<p>'.esc_html($item['text']??'').'</p>';
   if(!empty($node['elements']))$walk($node['elements']);
  }};$walk($tree);$out=[];$seen=[];$scores=[];
  foreach($chunks as $chunk){$clean=self::clean($chunk);$text=trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags($clean),ENT_QUOTES|ENT_HTML5,'UTF-8')));$fingerprint=preg_replace('/[^\pL\pN]+/u','',strtolower($text));if($fingerprint==='')continue;$score=substr_count($clean,'<')+10*substr_count($clean,'agx-l-');if(isset($seen[$fingerprint])){$index=$seen[$fingerprint];if($score>$scores[$index]){$out[$index]=$clean;$scores[$index]=$score;}continue;}$seen[$fingerprint]=count($out);$scores[]=$score;$out[]=$clean;}
  return implode("\n",$out);
 }
}
