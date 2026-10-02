import json,sys
A=json.load(open('/home/user/claude/agst/media/assign.json'));V=json.load(open('/home/user/claude/agst/media/videos.json'))
SYS={'alu20':'ALU 20 black slat','alu40':'ALU 40 black slat','alu40wg':'ALU 40 wood grain slat','tg':'ALU 60 T&G black','tgwg':'ALU 60 T&G wood grain',
 'slatroof':'Aluminum slat roof patio covers','louver':'Automatic louvered patio covers','insulated':'Insulated roof patio covers','cantislat':'Cantilever slat roof patio covers','clad':'Aluminum wall cladding'}
def title(imgs):
    pools=[i['pool'] for i in imgs]; p=max(set(pools),key=pools.count)
    if p in('slatroof','louver','insulated','cantislat'): return SYS[p]+' we have built'
    if p=='clad': return 'Aluminum wall cladding in real projects'
    ts={i['type'] for i in imgs}; kind=' and '.join(x for x in (['fences'] if 'F' in ts else [])+(['gates'] if ts&{'P','D'} else []))
    return f"{SYS[p]} {kind} in real projects"
vid={}
for g in V['groups'].values():
    for pid in g['products']:
        for y in g['videos']:
            vid.setdefault(str(pid),[]);
            if y not in vid[str(pid)]: vid[str(pid)].append(y)
ids=sys.argv[1].split(',') if len(sys.argv)>1 and sys.argv[1]!='all' else sorted(set(A)|set(vid))
out={}
for pid in ids:
    a=A.get(pid);d={}
    if a:
        d['slug']=a['slug'];d['title']=title(a['images'])
        d['intro']='Completed installations by Aluglobus Aluminum Systems using this system. Sizes, layouts and accessories vary by project.'
        d['images']=[{k:i[k] for k in('file','clean','alt','caption')} for i in a['images']]
    d['videos']=[{'id':y,'title':V['videos'][y]} for y in vid.get(pid,[])][:6]
    out[pid]=d
json.dump(out,open('payload.json','w'))
print(len(out),'products', sum(len(v.get('images',[])) for v in out.values()),'images', sum(len(v['videos']) for v in out.values()),'video slots')
