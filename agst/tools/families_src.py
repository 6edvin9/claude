import json
P={x['id']:x for x in json.load(open('products.json'))}
slug=lambda i:P[i]['url'].rstrip('/').split('/')[-1]
F=[
 {"key":"hd-gate","title":"Heavy duty (HD) gate frame system",
  "text":"Parts of the Aluglobus Aluminum Systems HD gate range: the 2×4 in. HD aluminum gate frame profiles and frame kits, the big and small HD L corner brackets, heavy duty double-support hinges, L-stoppers and the fasteners the price list names for these frames.",
  "members":[63453,63456,63458,63446,63449,63451,63432,63429,63443,26616,63438,63435,63426,63411,63420,26639,26665],
  "steps":["Choose the gate type (HD pedestrian or HD sliding) and the finished opening size.","Pick the 2×4 in. HD frame length and wall thickness listed for that size.","Add the corner brackets, hinges or sliding hardware the frame needs.","Add the listed fasteners, sold per piece, with a few spares.","Send the opening size and photos if you want us to check the parts list."]},
 {"key":"box-gate","title":"Box DIY pedestrian gate system",
  "text":"The boxed DIY pedestrian gate kits and the parts sold for them: 2×2 in. hinge posts and L-side posts for wall installation, and the gate slat boxes in ALU 40 and ALU 60 T&G.",
  "members":[63722,63730,63768,63776,63187,63233,63218,63190,63221,63717,63169,63712,63440,63443,63438],
  "steps":["Measure the opening between the two walls (these posts are for wall installation).","Choose the kit size (4×6 or 6×6 ft) and the slat style (ALU 40 with a gap, or ALU 60 T&G with no gap).","Choose the color offered for the kit.","Add hinge post / L-side post sets and slat boxes only if you are building or replacing parts.","Confirm availability for colors marked coming soon."]},
 {"key":"universal-diy","title":"Universal DIY gate system",
  "text":"Universal DIY pedestrian gate kits and the matching DIY gate frame kits. The frames are designed to go together without welding and take ALU 20, ALU 40 or ALU 50/60 T&G infill depending on the kit.",
  "members":[38472,26856,26834,26832,38548,39398,38607,63796,39288,38112,38101,63443,63438,63435,63440,26639,26665],
  "steps":["Measure the opening and check it is a wall-to-wall installation, or ask about the HD post option.","Choose the size (4×6, 6×6 or 6×8 ft frame) and the infill: ALU 20, ALU 40 or T&G.","Choose black or wood grain where offered.","Add a latch, cane bolt or stopper if your layout needs one.","Send photos of the opening if you are unsure about hinge side or swing direction."]},
 {"key":"post-275","title":"2.75 2-way post fence system",
  "text":"Fence parts built around the 2.75 in. 2-way aluminum post: posts and boxed post kits, post spacers, caps and base plates, plus the ALU 40 and ALU 60 T&G slats and top rails used with them.",
  "members":[63253,63738,37442,60784,63201,63235,63209,63243,63249,63748,63751,37957,37960,37962,60752,38016,63746,26675,63405,63408,63283,63276,63302,63299,49286,26587,63291,49300,26603,63797],
  "steps":["Measure the fence run and decide the post spacing (8 ft sections for the kits).","Pick the slat: ALU 40 with a 1/2 in. gap or ALU 60 T&G.","Choose the spacer size that sets the gap you want.","Choose how posts are installed: in concrete or on a base plate (anchors sold separately).","Add caps, top rails and screws, then confirm the color for every part."]},
 {"key":"square-post","title":"Square post and C-channel fence system",
  "text":"Square aluminum posts (2×2, 3×3, 3.15 and 4×4 in.) with C-channel kits, C-channel and channel-cover spacers, press caps and base plates, as used on the Aluglobus fence kits and gates.",
  "members":[38464,26830,38571,63264,63261,63149,37478,37481,37475,37472,60092,60071,60025,63215,26565,26562,38130,38126,63761,63759,63273,63270,60122,60062,60033,38089,63267,26597,63408,63405],
  "steps":["Choose the post size and wall thickness for the job (light duty or HD).","Match the cap to the post size (2, 3, 3.15 or 4 in.).","Add C-channel kits for each post face that holds slats, and the spacers that set the gap.","Decide between posts set in concrete and base plates (anchors sold separately).","Confirm color and lengths before ordering."]},
 {"key":"sliding","title":"Aluminum sliding gate system",
  "text":"Horizontal aluminum sliding gate kits and the sliding hardware sold for them: C track, wheels, guide rollers, gate catcher and stopper, and the bolts the price list lists for those parts.",
  "members":[26860,26862,38397,38394,60355,60357,60359,60361,38615,38627,63798,38201,38198,26649,26647,26667,26659,63399,63402,63426],
  "steps":["Measure the driveway opening and check there is room for the gate to slide to one side.","Choose the infill (ALU 20, ALU 40, ALU 60 T&G or wood grain) and the size (12, 18 or 19 ft).","Plan the track, wheels, guide rollers, catcher and stopper for your layout.","Decide whether you will add a gate operator (not included unless listed).","Send photos of the driveway so we can check the layout."]},
 {"key":"aerolouver","title":"AeroLouver privacy fence profiles",
  "text":"AeroLouver post, blade and Max Rail profiles for decorative privacy fencing.",
  "members":[38143,38146,38149,38152,63764],
  "steps":["Measure the fence run and post locations.","Choose the blade and rail profiles for the look you want.","Plan post heights; posts are cut on site.","Request pricing for items marked price on request."]},
 {"key":"cladding","title":"Aluminum wall cladding",
  "text":"Tongue-and-groove aluminum cladding panels (CLAD100 and CLAD120) and the L-shape cladding profile.",
  "members":[32975,32907,33037],
  "steps":["Measure each wall and add waste for cuts and openings.","Choose the panel (CLAD100 or CLAD120) and the color.","Confirm the substrate and fastening with our team before ordering."]},
 {"key":"patio","title":"Aluminum patio covers and pergolas",
  "text":"Factory-direct aluminum patio cover and pergola systems: slat roof, beam roof, insulated roof and motorized louvered roofs, in standard and cantilever versions, plus the boxed DIY louvered kit.",
  "members":[57987,58015,58025,58030,58036,58678,58684,63803,63414,63417,63426],
  "steps":["Measure the area and decide attached or freestanding.","Choose the roof type: slat, beam, insulated or louvered.","Choose the color and any lighting or motor options listed for the system.","Check permits and engineering needs with your local authority.","Send plans or photos so we can prepare the package quote."]},
 {"key":"slats","title":"Aluminum slats and infill",
  "text":"ALU 20, ALU 40 and ALU 60 T&G slats, in black and wood grain finishes, sold individually or in boxes for fences and gates.",
  "members":[26805,63283,63276,63302,63299,63221,63717,26550,26551,63757,63754,49286,26587,60254,26547,63169,63712,63423],
  "steps":["Count slats per section from the fence height and the gap you want.","Match the slat to your system: ALU 20, ALU 40 or ALU 60 T&G.","Choose the color or wood grain finish and check it is available.","Add the slat fasteners listed for your system."]}
]
for f in F:
    f['members']=[slug(i) for i in f['members'] if i in P]
json.dump(F,open('/home/user/claude/agst/content/families.json','w'),indent=1,ensure_ascii=False)
ids=[i for i in P]; cov={s for f in F for s in f['members']}
print('covered',len(cov),'of',len(P)); print('missing',[ (i,P[i]['name'][:40]) for i in P if slug(i) not in cov])
