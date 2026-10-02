import sys,json
for l in sys.stdin:
  p=l.split(' ',2)
  if len(p)<3: continue
  k,m,j=p
  try: d=json.loads(j)
  except: print(l.strip()[:250]); continue
  print(k,m,'sec',d['sections'],'nest',d['nested'],'empty',[x for x in d['emptyHref'] if any(w in x for w in ('Request','Quote','Get','View','Price','Contact','Call','Shop','Order','See'))][:3],'raw',d['raw'],'low',d['lowCount'],d['low'][:3],'sw',d['scrollW'])
