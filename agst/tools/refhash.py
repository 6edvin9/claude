# GD-compatible 256-bit dHash of the gallery thumbnail (reference for the server-side check)
from PIL import Image
def ref(path):
    im=Image.open(path).convert('RGB').resize((17,16),Image.BILINEAR);px=im.load();b=''
    for y in range(16):
        for x in range(16):
            a=px[x,y];c=px[x+1,y];b+='1' if a[0]*299+a[1]*587+a[2]*114>c[0]*299+c[1]*587+c[2]*114 else '0'
    return b
