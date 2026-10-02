import json,glob,re
R=json.load(open('/home/user/claude/agst/media/rules.json'))
m={x['id']:x for x in json.load(open('gal_media.json'))}
clean=json.load(open('clean_src.json'))
P={x['id']:x for x in json.load(open('products.json'))}
pools={}
for f in glob.glob('cls_*.txt'):
    name=f[4:-4]; pools[name]=[]
    for l in open(f):
        if l.startswith('#') or not l.strip(): continue
        i,c=l.split(); pools[name].append((int(i),c))
TYPE={'F':'fence','P':'pedestrian gate','D':'driveway gate','W':'wood grain cladding','B':'black cladding','*':''}
NAME={'alu20':'ALU 20 black slat','alu40':'ALU 40 black slat','alu40wg':'ALU 40 wood grain slat','tg':'ALU 60 T&G black','tgwg':'ALU 60 T&G wood grain',
      'slatroof':'Aluminum slat roof patio cover','louver':'Automatic louvered patio cover','insulated':'Insulated roof patio cover','cantislat':'Cantilever slat roof patio cover','clad':'Aluminum wall cladding'}
def usable(i):
    x=m[i];c=clean.get(str(i))
    if c: return c
    if x['date']>='2026-03': return {'kind':'self','path':re.sub(r'^https?://[^/]+/wp-content/uploads/','',x['url']),'w':x['w'],'h':x['h']}
    return None  # watermarked, no clean original
out={};skipped=0
for pid,r in R['products'].items():
    pid=int(pid);sel=[];seen=set()
    for spec in r['pick']:
        pool,cls=spec.split(':')
        cand=[(i,c) for i,c in pools[pool] if (cls=='*' or c==cls) and c!='X' and i not in seen]
        cand.sort(key=lambda t:(m[t[0]]['date'][:7],(usable(t[0]) or {}).get('w',0) or 0),reverse=True)
        for i,c in cand:
            if len(sel)>=r['max']: break
            u=usable(i)
            if not u: skipped+=1; continue
            seen.add(i)
            t=TYPE[c]; label=NAME[pool]+(' '+t if t else '')
            sel.append({'src_id':i,'file':re.sub(r'^https?://[^/]+/wp-content/uploads/','',m[i]['url']),'clean':u['path'],'clean_kind':u['kind'],'w':u.get('w'),'h':u.get('h'),'date':m[i]['date'][:10],'type':c,'pool':pool,
                        'alt':f"{label[0].upper()+label[1:]} installed by Aluglobus Aluminum Systems",'caption':f"{label[0].upper()+label[1:]} project"})
    out[pid]={'slug':P[pid]['url'].rstrip('/').split('/')[-1],'name':P[pid]['name'],'images':sel}
json.dump(out,open('/home/user/claude/agst/media/assign.json','w'),indent=1,ensure_ascii=False)
print('products',len(out),'images total',sum(len(v['images']) for v in out.values()),'unique',len({i['src_id'] for v in out.values() for i in v['images']}),'skipped(watermarked)',skipped)
for k,v in out.items(): print(k,len(v['images']),v['name'][:50])
