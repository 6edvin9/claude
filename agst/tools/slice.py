# python3 slice.py shots/X.png [width] -> shots/X-s1.jpg ... (downscaled slices for viewing)
import sys; from PIL import Image
Image.MAX_IMAGE_PIXELS=None
p=sys.argv[1]; W=int(sys.argv[2]) if len(sys.argv)>2 else 900
im=Image.open(p).convert('RGB'); s=W/im.width if im.width>W else 1
im=im.resize((int(im.width*s),int(im.height*s)))
H=int(sys.argv[3]) if len(sys.argv)>3 else 1800
n=0
for y in range(0,im.height,H):
    n+=1; im.crop((0,y,im.width,min(im.height,y+H))).save(p[:-4]+f'-s{n}.jpg',quality=70)
print(p, im.size, n)
