import json,sys,os
from PIL import Image,ImageDraw,ImageFont
m=json.load(open('gal_media.json'))
def sheets(name,xs,cols=6,rows=7,tw=230,th=172):
    xs=sorted(xs,key=lambda x:x['date'],reverse=True)
    per=cols*rows;out=[]
    try: f=ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',15)
    except: f=ImageFont.load_default()
    for k in range(0,len(xs),per):
        S=Image.new('RGB',(cols*tw,rows*th),(40,40,40));d=ImageDraw.Draw(S)
        for n,x in enumerate(xs[k:k+per]):
            try: im=Image.open(f"thumbs/{x['id']}.jpg");im.thumbnail((tw-4,th-4))
            except: continue
            X=(n%cols)*tw;Y=(n//cols)*th;S.paste(im,(X+2,Y+2))
            lab=f"{x['id']} {x['date'][2:7]} {x['w']}"
            d.rectangle((X+2,Y+2,X+8+len(lab)*9,Y+22),fill=(0,0,0));d.text((X+5,Y+4),lab,fill=(255,255,0),font=f)
        p=f"sheets/{name}-{k//per+1}.jpg";S.save(p,quality=72);out.append(p)
    return out
if __name__=='__main__':
    tag=sys.argv[1];name=sys.argv[2]
    xs=[x for x in m if tag in x['tags'] and (x['w'] or 0)>=900]
    print(sheets(name,xs))
