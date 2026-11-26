"""
Mrs Chow's Kitchen — hero noodle bowl (scroll sequence)
=======================================================
Builds a photoreal bowl of egg noodles in broth with lacquered chopsticks
lifting a tangle of noodles out, then renders a frame sequence that the
website scrubs on scroll.

Run headless:
  /Applications/Blender.app/Contents/MacOS/Blender -b -P noodle_bowl.py -- \
      [--frames 0,30,59] [--samples 96] [--res 1080] [--count 60] [--save]

Scene units: metres, real-world scale (bowl Ø 18 cm).
"""
import bpy, bmesh, math, random, sys, os
from mathutils import Vector, Matrix

# ---------------------------------------------------------------- args
argv = sys.argv[sys.argv.index("--") + 1:] if "--" in sys.argv else []
def arg(name, default):
    if name in argv:
        return argv[argv.index(name) + 1]
    return default
COUNT   = int(arg("--count", 60))
SAMPLES = int(arg("--samples", 96))
RES     = int(arg("--res", 1080))
FRAMES  = [int(f) for f in arg("--frames", ",".join(str(i) for i in range(COUNT))).split(",")]
SAVE    = "--save" in argv
HERE    = os.path.dirname(os.path.abspath(__file__))
OUT     = os.path.join(HERE, "render")
os.makedirs(OUT, exist_ok=True)

random.seed(7)

# ---------------------------------------------------------------- reset
bpy.ops.wm.read_factory_settings(use_empty=True)
scene = bpy.context.scene

# ---------------------------------------------------------------- helpers
def mat(name):
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    return m, m.node_tree.nodes, m.node_tree.links, m.node_tree.nodes["Principled BSDF"]

def setp(bsdf, **kw):
    names = {
        "base": "Base Color", "rough": "Roughness", "metal": "Metallic",
        "sss": "Subsurface Weight", "sss_radius": "Subsurface Radius", "sss_scale": "Subsurface Scale",
        "coat": "Coat Weight", "coat_rough": "Coat Roughness", "spec": "Specular IOR Level",
        "ior": "IOR", "trans": "Transmission Weight", "sheen": "Sheen Weight", "alpha": "Alpha",
    }
    for k, v in kw.items():
        bsdf.inputs[names[k]].default_value = v

def link_obj(obj):
    scene.collection.objects.link(obj)
    return obj

def smooth(obj, level=2):
    for p in obj.data.polygons:
        p.use_smooth = True
    if level:
        m = obj.modifiers.new("Subd", "SUBSURF")
        m.levels = level
        m.render_levels = level

def lerp(a, b, t):
    return a + (b - a) * t

def ease_in_out(t):
    return t * t * (3 - 2 * t)

def ease_out(t):
    return 1 - (1 - t) ** 3

# ---------------------------------------------------------------- bowl
# Profile (radius, height) from the foot, up the outside, over the rim, down the inside.
OUTER = [(0.000, 0.000), (0.038, 0.000), (0.040, 0.002), (0.041, 0.008), (0.046, 0.010),
         (0.062, 0.020), (0.076, 0.036), (0.086, 0.055), (0.091, 0.072), (0.093, 0.080)]
RIM   = [(0.0925, 0.0825), (0.0905, 0.0828)]
INNER = [(0.0890, 0.0800), (0.0855, 0.0640), (0.0770, 0.0460), (0.0640, 0.0300),
         (0.0450, 0.0180), (0.0220, 0.0130), (0.000, 0.0120)]
PROFILE = OUTER + RIM + INNER
INNER_START = len(OUTER) + len(RIM)

def inner_radius_at(z):
    pts = INNER
    for (r1, z1), (r2, z2) in zip(pts, pts[1:]):
        if z2 <= z <= z1:
            t = (z - z1) / (z2 - z1)
            return lerp(r1, r2, t)
    return pts[-1][0]

bm = bmesh.new()
verts = [bm.verts.new((r, 0, z)) for r, z in PROFILE]
edges = [bm.edges.new((verts[i], verts[i + 1])) for i in range(len(verts) - 1)]
bmesh.ops.spin(bm, geom=verts + edges, cent=(0, 0, 0), axis=(0, 0, 1), angle=math.tau, steps=96, use_merge=True)
bmesh.ops.remove_doubles(bm, verts=bm.verts, dist=1e-6)
bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
bm.faces.ensure_lookup_table()
for f in bm.faces:
    c = f.calc_center_median()
    radial = Vector((c.x, c.y, 0))
    radial = radial.normalized() if radial.length > 1e-6 else Vector((0, 0, 0))
    n = f.normal
    if n.z > 0.85 and c.z > 0.08:
        f.material_index = 2          # rim band
    elif n.dot(radial) < -0.05 or (c.z < 0.02 and n.z > 0.5 and radial.length == 0) or (c.z < 0.016 and n.z > 0.6):
        f.material_index = 1          # glazed interior
    else:
        f.material_index = 0          # red exterior
bowl_me = bpy.data.meshes.new("Bowl")
bm.to_mesh(bowl_me)
bm.free()
bowl = link_obj(bpy.data.objects.new("Bowl", bowl_me))
smooth(bowl, 2)

m_out, n, l, b = mat("Glaze_Red")
setp(b, base=(0.30, 0.03, 0.02, 1), rough=0.16, coat=1.0, coat_rough=0.09, spec=0.5)
# subtle glaze pooling: darker toward the foot
tc = n.new("ShaderNodeTexCoord"); sep = n.new("ShaderNodeSeparateXYZ"); ramp = n.new("ShaderNodeValToRGB")
l.new(tc.outputs["Object"], sep.inputs[0]); l.new(sep.outputs["Z"], ramp.inputs[0])
ramp.color_ramp.elements[0].position = 0.0; ramp.color_ramp.elements[0].color = (0.20, 0.02, 0.015, 1)
ramp.color_ramp.elements[1].position = 0.06; ramp.color_ramp.elements[1].color = (0.34, 0.035, 0.025, 1)
l.new(ramp.outputs[0], b.inputs["Base Color"])

m_in, n, l, b = mat("Glaze_Cream")
setp(b, base=(0.86, 0.82, 0.74, 1), rough=0.08, coat=0.6, coat_rough=0.02, sss=0.05)

m_rim, n, l, b = mat("Glaze_Rim")
setp(b, base=(0.55, 0.40, 0.18, 1), metal=0.85, rough=0.22)   # thin gilt rim
for m in (m_out, m_in, m_rim):
    bowl.data.materials.append(m)

# ---------------------------------------------------------------- broth
BROTH_Z = 0.058
broth_r = inner_radius_at(BROTH_Z) - 0.0004
bpy.ops.mesh.primitive_circle_add(vertices=128, radius=broth_r, fill_type="NGON", location=(0, 0, BROTH_Z))
broth = bpy.context.object; broth.name = "Broth"
m_broth, n, l, b = mat("Broth")
setp(b, base=(0.32, 0.12, 0.025, 1), rough=0.04, spec=0.7, coat=0.0)
# chilli-oil and fat speckle: voronoi -> mix into colour + bump ripples
vor = n.new("ShaderNodeTexVoronoi"); vor.inputs["Scale"].default_value = 70
noi = n.new("ShaderNodeTexNoise"); noi.inputs["Scale"].default_value = 18; noi.inputs["Detail"].default_value = 6
rp = n.new("ShaderNodeValToRGB")
rp.color_ramp.elements[0].position = 0.0;  rp.color_ramp.elements[0].color = (0.55, 0.08, 0.02, 1)
rp.color_ramp.elements[1].position = 0.12; rp.color_ramp.elements[1].color = (0.32, 0.12, 0.025, 1)
l.new(vor.outputs["Distance"], rp.inputs[0]); l.new(rp.outputs[0], b.inputs["Base Color"])
bump = n.new("ShaderNodeBump"); bump.inputs["Strength"].default_value = 0.08; bump.inputs["Distance"].default_value = 0.0005
l.new(noi.outputs["Fac"], bump.inputs["Height"]); l.new(bump.outputs["Normal"], b.inputs["Normal"])
broth.data.materials.append(m_broth)

# ---------------------------------------------------------------- noodle material
m_noodle, n, l, b = mat("Egg_Noodle")
setp(b, base=(0.80, 0.52, 0.16, 1), rough=0.26, sss=0.35, sss_radius=(0.8, 0.45, 0.2), sss_scale=0.003, coat=0.35, coat_rough=0.12, sheen=0.1)
noi = n.new("ShaderNodeTexNoise"); noi.inputs["Scale"].default_value = 300
rp = n.new("ShaderNodeValToRGB")
rp.color_ramp.elements[0].color = (0.70, 0.42, 0.11, 1); rp.color_ramp.elements[1].color = (0.86, 0.58, 0.20, 1)
l.new(noi.outputs["Fac"], rp.inputs[0]); l.new(rp.outputs[0], b.inputs["Base Color"])

NOODLE_R = 0.0016

def make_curve(name, paths, material, radius=NOODLE_R):
    cu = bpy.data.curves.new(name, "CURVE")
    cu.dimensions = "3D"
    cu.bevel_depth = radius
    cu.bevel_resolution = 3
    cu.use_fill_caps = True
    cu.resolution_u = 6
    for pts in paths:
        sp = cu.splines.new("NURBS")
        sp.points.add(len(pts) - 1)
        for p, co in zip(sp.points, pts):
            p.co = (co[0], co[1], co[2], 1)
        sp.order_u = 4
        sp.use_endpoint_u = True
        sp.use_smooth = True
    cu.materials.append(material)
    ob = bpy.data.objects.new(name, cu)
    return link_obj(ob)

# ---------------------------------------------------------------- noodle nest (in the bowl)
PICK = Vector((0.008, -0.004))          # where the chopsticks dive in
def nest_paths(n_strands=46):
    paths = []
    for s in range(n_strands):
        ang = random.uniform(0, math.tau)
        rad = random.uniform(0.0, 0.055)
        x, y = math.cos(ang) * rad, math.sin(ang) * rad
        heading = random.uniform(0, math.tau)
        pts = []
        steps = random.randint(26, 46)
        phase = random.uniform(0, math.tau)
        for i in range(steps):
            heading += random.uniform(-0.55, 0.55)
            x += math.cos(heading) * 0.0042
            y += math.sin(heading) * 0.0042
            r = math.hypot(x, y)
            lim = broth_r - 0.006
            if r > lim:                     # turn back at the bowl wall
                heading += math.pi * 0.8
                x, y = x / r * lim, y / r * lim
            # pile higher toward the middle, ripple in and out of the broth
            pile = 0.006 * max(0.0, 1 - r / 0.06)
            z = BROTH_Z - 0.0012 + pile + 0.0016 * math.sin(i * 0.9 + phase) + random.uniform(-0.0004, 0.0004)
            pts.append((x, y, z))
        paths.append(pts)
    return paths
nest = make_curve("Noodle_Nest", nest_paths(), m_noodle)

# ---------------------------------------------------------------- toppings
def egg():
    # halved soft-boiled egg, cut face up and tilted toward camera
    bpy.ops.mesh.primitive_uv_sphere_add(segments=48, ring_count=24, radius=1, location=(0, 0, 0))
    e = bpy.context.object; e.name = "Egg_White"
    bm = bmesh.from_edit_mesh(e.data) if e.mode == "EDIT" else None
    me = e.data
    bm = bmesh.new(); bm.from_mesh(me)
    bmesh.ops.bisect_plane(bm, geom=bm.verts[:] + bm.edges[:] + bm.faces[:], plane_co=(0, 0, 0), plane_no=(0, 0, 1), clear_outer=True)
    edges = [e_ for e_ in bm.edges if e_.is_boundary]
    bmesh.ops.edgeloop_fill(bm, edges=edges)
    bm.to_mesh(me); bm.free()
    e.scale = (0.0165, 0.0225, 0.0165)
    smooth(e, 1)
    m, n, l, b = mat("Egg_White")
    setp(b, base=(0.92, 0.88, 0.80, 1), rough=0.25, sss=0.4, sss_radius=(1, 0.9, 0.8), sss_scale=0.004)
    e.data.materials.append(m)
    # jammy yolk sitting in the cut face
    bpy.ops.mesh.primitive_uv_sphere_add(segments=32, ring_count=16, radius=1, location=(0, 0, 0))
    y = bpy.context.object; y.name = "Egg_Yolk"
    y.scale = (0.0085, 0.0095, 0.0040)
    smooth(y, 1)
    m, n, l, b = mat("Egg_Yolk")
    setp(b, base=(0.92, 0.42, 0.04, 1), rough=0.12, sss=0.5, sss_radius=(1, 0.5, 0.1), sss_scale=0.003, coat=0.6)
    y.data.materials.append(m)
    y.parent = e
    y.location = (0, 0, 0)         # centred on the cut face (local units)
    y.scale = (0.62, 0.50, 0.28)
    e.location = (-0.040, -0.028, BROTH_Z + 0.004)
    e.rotation_euler = (math.radians(38), math.radians(6), math.radians(-24))   # cut face toward camera
egg()

def char_siu(i, loc, rot):
    bpy.ops.mesh.primitive_cube_add(size=1, location=loc)
    c = bpy.context.object; c.name = f"CharSiu_{i}"
    c.scale = (0.030, 0.022, 0.0042)
    c.rotation_euler = rot
    bev = c.modifiers.new("Bevel", "BEVEL"); bev.width = 0.0015; bev.segments = 3
    smooth(c, 1)
    m = bpy.data.materials.get("CharSiu")
    if not m:
        m, n, l, b = mat("CharSiu")
        setp(b, rough=0.38, coat=0.5, coat_rough=0.2, sss=0.15, sss_radius=(1, 0.3, 0.2), sss_scale=0.002)
        tc = n.new("ShaderNodeTexCoord"); sep = n.new("ShaderNodeSeparateXYZ")
        mth = n.new("ShaderNodeMath"); mth.operation = "ABSOLUTE"
        mx = n.new("ShaderNodeMath"); mx.operation = "MAXIMUM"
        sep2 = n.new("ShaderNodeMath"); sep2.operation = "ABSOLUTE"
        l.new(tc.outputs["Generated"], sep.inputs[0])
        # generated coords 0..1 -> distance from centre in x/y -> red glazed edge
        sub = n.new("ShaderNodeMath"); sub.operation = "SUBTRACT"; sub.inputs[1].default_value = 0.5
        sub2 = n.new("ShaderNodeMath"); sub2.operation = "SUBTRACT"; sub2.inputs[1].default_value = 0.5
        l.new(sep.outputs["X"], sub.inputs[0]); l.new(sep.outputs["Y"], sub2.inputs[0])
        l.new(sub.outputs[0], mth.inputs[0]); l.new(sub2.outputs[0], sep2.inputs[0])
        l.new(mth.outputs[0], mx.inputs[0]); l.new(sep2.outputs[0], mx.inputs[1])
        nz = n.new("ShaderNodeTexNoise"); nz.inputs["Scale"].default_value = 40
        add = n.new("ShaderNodeMath"); add.operation = "ADD"
        scl = n.new("ShaderNodeMath"); scl.operation = "MULTIPLY"; scl.inputs[1].default_value = 0.08
        l.new(nz.outputs["Fac"], scl.inputs[0]); l.new(mx.outputs[0], add.inputs[0]); l.new(scl.outputs[0], add.inputs[1])
        rp = n.new("ShaderNodeValToRGB")
        rp.color_ramp.elements[0].position = 0.30; rp.color_ramp.elements[0].color = (0.50, 0.27, 0.17, 1)
        rp.color_ramp.elements[1].position = 0.43; rp.color_ramp.elements[1].color = (0.22, 0.035, 0.015, 1)
        l.new(add.outputs[0], rp.inputs[0]); l.new(rp.outputs[0], b.inputs["Base Color"])
    c.data.materials.append(m)
char_siu(1, (0.040, 0.030, BROTH_Z + 0.006), (math.radians(14), math.radians(-6), math.radians(35)))
char_siu(2, (0.052, 0.006, BROTH_Z + 0.004), (math.radians(10), math.radians(-12), math.radians(70)))
char_siu(3, (0.030, 0.048, BROTH_Z + 0.004), (math.radians(8), math.radians(4), math.radians(10)))

# bok choy: a halved head — stalk + two cupped leaves
def bok_choy():
    m_st, n, l, b = mat("BokChoy_Stalk")
    setp(b, base=(0.74, 0.82, 0.55, 1), rough=0.25, sss=0.4, sss_radius=(0.6, 1, 0.5), sss_scale=0.003, coat=0.4)
    m_lf, n, l, b = mat("BokChoy_Leaf")
    setp(b, base=(0.035, 0.15, 0.025, 1), rough=0.3, sss=0.2, sss_radius=(0.4, 1, 0.3), sss_scale=0.002, coat=0.5, coat_rough=0.15)
    for k, (loc, rz) in enumerate((((-0.012, 0.036, BROTH_Z + 0.003), 105), ((-0.036, 0.012, BROTH_Z + 0.003), 150))):
        bpy.ops.mesh.primitive_uv_sphere_add(segments=32, ring_count=16, radius=1, location=loc)
        st = bpy.context.object; st.name = f"BokChoy_Stalk_{k}"
        st.scale = (0.024, 0.010, 0.0045); st.rotation_euler = (0, 0, math.radians(rz)); smooth(st, 1)
        st.data.materials.append(m_st)
        d = Vector((math.cos(math.radians(rz)), math.sin(math.radians(rz)), 0))
        bpy.ops.mesh.primitive_uv_sphere_add(segments=32, ring_count=16, radius=1, location=Vector(loc) + d * 0.030 + Vector((0, 0, 0.002)))
        lf = bpy.context.object; lf.name = f"BokChoy_Leaf_{k}"
        lf.location = Vector(loc) + d * 0.022 + Vector((0, 0, 0.003))
        lf.scale = (0.030, 0.022, 0.0022); lf.rotation_euler = (math.radians(6), math.radians(-4), math.radians(rz)); smooth(lf, 2)
        dsp = lf.modifiers.new("Wrinkle", "DISPLACE")
        tex = bpy.data.textures.new(f"leaf_noise_{k}", "CLOUDS"); tex.noise_scale = 0.18; tex.noise_depth = 4
        dsp.texture = tex; dsp.direction = 'Z'; dsp.strength = 1.2
        lf.data.materials.append(m_lf)
bok_choy()

# spring onion rings
m_on, n, l, b = mat("Spring_Onion")
setp(b, base=(0.09, 0.34, 0.05, 1), rough=0.3, sss=0.3, sss_radius=(0.5, 1, 0.4), sss_scale=0.002, coat=0.5)
for i in range(26):
    a = random.uniform(0, math.tau); r = random.uniform(0.01, 0.06)
    p = (math.cos(a) * r, math.sin(a) * r, BROTH_Z + 0.004 + random.uniform(0, 0.004))
    bpy.ops.mesh.primitive_torus_add(major_radius=0.0032, minor_radius=0.0007, major_segments=24, minor_segments=8, location=p,
                                     rotation=(random.uniform(-0.5, 0.5), random.uniform(-0.5, 0.5), random.uniform(0, 6.28)))
    o = bpy.context.object; o.name = f"Onion_{i}"
    o.scale = (1, 1, random.uniform(1.2, 2.2))
    for pp in o.data.polygons: pp.use_smooth = True
    o.data.materials.append(m_on)

# sesame seeds
m_ses, n, l, b = mat("Sesame")
setp(b, base=(0.85, 0.74, 0.52, 1), rough=0.35)
for i in range(40):
    a = random.uniform(0, math.tau); r = random.uniform(0.0, 0.05)
    bpy.ops.mesh.primitive_uv_sphere_add(segments=10, ring_count=6, radius=1,
        location=(math.cos(a) * r, math.sin(a) * r, BROTH_Z + 0.006 + random.uniform(0, 0.003)),
        rotation=(random.uniform(0, 3), random.uniform(0, 3), random.uniform(0, 3)))
    o = bpy.context.object; o.name = f"Sesame_{i}"
    o.scale = (0.0016, 0.0009, 0.0005)
    o.data.materials.append(m_ses)

# ---------------------------------------------------------------- chopsticks
m_lac, n, l, b = mat("Lacquer_Black")
setp(b, base=(0.012, 0.010, 0.009, 1), rough=0.18, coat=1.0, coat_rough=0.04)
m_gold, n, l, b = mat("Gold_Band")
setp(b, base=(0.80, 0.58, 0.25, 1), metal=1.0, rough=0.22)
m_redtip, n, l, b = mat("Lacquer_Red")
setp(b, base=(0.40, 0.035, 0.025, 1), rough=0.2, coat=1.0, coat_rough=0.05)

STICK_L = 0.235

# densify the sticks so the bands have edges to sit on
def dense_stick(name):
    bpy.ops.mesh.primitive_cylinder_add(vertices=24, radius=1, depth=1)
    s = bpy.context.object; s.name = name
    me = s.data
    bm = bmesh.new(); bm.from_mesh(me)
    bmesh.ops.delete(bm, geom=bm.verts[:], context="VERTS")
    rings = 80
    ring_verts = []
    for i in range(rings + 1):
        t = i / rings
        z = t * STICK_L
        r = lerp(0.00125, 0.0034, t)
        if t > 0.985:                      # rounded top
            r *= math.cos((t - 0.985) / 0.015 * math.pi / 2) * 0.6 + 0.4
        if t < 0.01:                       # rounded tip
            r *= 0.55 + 0.45 * (t / 0.01)
        ring = [bm.verts.new((math.cos(a) * r, math.sin(a) * r, z)) for a in (k / 24 * math.tau for k in range(24))]
        ring_verts.append(ring)
    for i in range(rings):
        a, b_ = ring_verts[i], ring_verts[i + 1]
        for k in range(24):
            bm.faces.new((a[k], a[(k + 1) % 24], b_[(k + 1) % 24], b_[k]))
    bm.faces.new(list(reversed(ring_verts[0]))); bm.faces.new(ring_verts[-1])
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    bm.to_mesh(me); bm.free()
    s.scale = (1, 1, 1)
    for p in me.polygons:
        p.use_smooth = True
        z = sum(me.vertices[i].co.z for i in p.vertices) / len(p.vertices)
        t = z / STICK_L
        p.material_index = 1 if (0.655 < t < 0.668 or 0.700 < t < 0.708) else (2 if t > 0.708 else 0)
    me.materials.append(m_lac); me.materials.append(m_gold); me.materials.append(m_redtip)
    return s

stick_a = dense_stick("Chopstick_A")
stick_b = dense_stick("Chopstick_B")

# ---------------------------------------------------------------- lifted noodles (rebuilt per frame)
STRANDS = []
for k in range(20):
    STRANDS.append({
        "u": random.uniform(-0.0045, 0.0045),             # across the pinch
        "v": random.uniform(0.002, 0.011),                # up the sticks
        "side": random.choice((-1, 1)),
        "ang": random.uniform(0, math.tau),               # where it enters the broth
        "rad": random.uniform(0.006, 0.036),
        "wav": random.uniform(0.0018, 0.0034),
        "len": random.uniform(0.008, 0.014),
        "ph": random.uniform(0, math.tau),
        "sway": random.uniform(-1, 1),
    })

def stick_frame(t):
    """Pinch point and stick directions for lift amount t (0..1)."""
    h = ease_in_out(t)
    P = Vector((PICK.x + 0.010 * h, PICK.y - 0.012 * h, lerp(BROTH_Z - 0.006, BROTH_Z + 0.150, h)))
    tilt = lerp(math.radians(58), math.radians(50), h)
    d = Vector((math.cos(tilt) * 0.95, math.cos(tilt) * 0.10, math.sin(tilt))).normalized()
    view = Vector((0, 1, -0.55)).normalized()
    perp = d.cross(view).normalized()
    return P, d, perp, h

def place_sticks(t):
    P, d, perp, h = stick_frame(t)
    for s, sign in ((stick_a, -1), (stick_b, 1)):
        tip = P + perp * sign * 0.0026
        top = P + d * STICK_L + perp * sign * 0.011
        axis = (top - tip).normalized()
        s.location = tip
        s.rotation_mode = "QUATERNION"
        s.rotation_quaternion = axis.to_track_quat("Z", "Y")
    return P, d, perp, h

lift_obj = None
def build_lift(t):
    global lift_obj
    if lift_obj:
        cu = lift_obj.data
        bpy.data.objects.remove(lift_obj, do_unlink=True)
        bpy.data.curves.remove(cu)
        lift_obj = None
    P, d, perp, h = stick_frame(t)
    height = P.z - BROTH_Z
    paths = []
    for s in STRANDS:
        top = P + perp * s["u"] + d * s["v"]
        # drape: the noodle goes over the stick, then hangs
        over = top + perp * (0.0022 * s["side"]) + Vector((0, 0, 0.0016))
        spread = s["rad"] * lerp(0.35, 1.0, ease_out(min(1, max(0, t * 1.4))))
        foot = Vector((PICK.x + math.cos(s["ang"]) * spread, PICK.y + math.sin(s["ang"]) * spread, BROTH_Z - 0.004))
        pts = [over + Vector((0, 0, 0.0005)), over, top]
        if height > 0.004:
            n_pts = max(6, int(height / 0.006))
            side_dir = Vector((math.cos(s["ang"] + 1.4), math.sin(s["ang"] + 1.4), 0))
            perp2 = side_dir.cross(Vector((0, 0, 1))).normalized()
            for i in range(1, n_pts + 1):
                f = i / n_pts
                p = top.lerp(foot, f)
                # gravity sag toward the vertical line under the pinch, plus egg-noodle crimp
                sag = math.sin(f * math.pi) * 0.010 * (1 - h * 0.5)
                p += (Vector((P.x, P.y, p.z)) - p) * (0.25 * math.sin(f * math.pi))
                # helical egg-noodle crimp: stronger toward the bottom where the strand is slack
                ph = f * height / s["len"] * math.tau + s["ph"]
                amp = s["wav"] * (0.45 + 0.75 * f) * (0.7 + 0.3 * (1 - h))
                p += side_dir * amp * math.sin(ph) + perp2 * amp * math.cos(ph)
                p += perp * s["sway"] * sag * 0.6
                pts.append(p)
        # under the surface: tuck back into the nest
        pts.append(foot + Vector((math.cos(s["ang"]) * 0.010, math.sin(s["ang"]) * 0.010, -0.004)))
        pts.append(foot + Vector((math.cos(s["ang"]) * 0.022, math.sin(s["ang"]) * 0.022, -0.006)))
        paths.append([tuple(p) for p in pts])
    lift_obj = make_curve("Noodle_Lift", paths, m_noodle)

# (v2: floating on the page — the table, backdrop, napkin and chilli dish of v1 are gone; see noodle_bowl_framed_v1.py)

# ---------------------------------------------------------------- lights
def area(name, loc, target, size, power, kelvin_rgb):
    ld = bpy.data.lights.new(name, "AREA"); ld.shape = "RECTANGLE"; ld.size = size[0]; ld.size_y = size[1]
    ld.energy = power; ld.color = kelvin_rgb
    ob = link_obj(bpy.data.objects.new(name, ld)); ob.location = loc
    ob.rotation_euler = (Vector(target) - Vector(loc)).to_track_quat("-Z", "Y").to_euler()
    return ob
area("Key_BackLeft",  (-0.45, 0.42, 0.55), (0, 0, 0.07), (0.6, 0.4), 18,  (1.0, 0.86, 0.70))   # rim + broth sheen
area("Fill_FrontRight", (0.55, -0.55, 0.30), (0, 0, 0.08), (1.6, 1.6), 6, (1.0, 0.93, 0.85))
area("Top_Soft",      (0.05, 0.05, 0.85), (0, 0, 0.05), (0.7, 0.7), 7,  (1.0, 0.95, 0.88))
area("Kicker_Right",  (0.40, 0.35, 0.25), (0.02, 0, 0.16), (0.25, 0.5), 6,  (1.0, 0.80, 0.60))  # catches the lifted noodles
area("Under_Bounce",  (0.0, -0.45, -0.25), (0, 0, 0.04), (0.8, 0.8), 4,  (1.0, 0.92, 0.82))   # lifts the bowl's underside

world = bpy.data.worlds.new("World"); scene.world = world; world.use_nodes = True
wn = world.node_tree.nodes; wl = world.node_tree.links
env = wn.new("ShaderNodeTexEnvironment")
hdr = os.path.join(os.path.dirname(bpy.app.binary_path), "..", "Resources", bpy.app.version_string[:3], "datafiles", "studiolights", "world", "interior.exr")
if os.path.exists(hdr):
    env.image = bpy.data.images.load(hdr)
    wl.new(env.outputs["Color"], wn["Background"].inputs["Color"])
wn["Background"].inputs["Strength"].default_value = 0.16

# ---------------------------------------------------------------- camera
cam_d = bpy.data.cameras.new("Camera"); cam_d.lens = 60; cam_d.sensor_fit = 'AUTO'; cam_d.sensor_width = 36; cam_d.sensor_width = 36
cam_d.dof.use_dof = False
cam = link_obj(bpy.data.objects.new("Camera", cam_d)); scene.camera = cam
cam.location = (0.045, -0.80, 0.42)
aim = Vector((0.048, 0.0, 0.180))
cam.rotation_euler = (aim - cam.location).to_track_quat("-Z", "Y").to_euler()
focus = link_obj(bpy.data.objects.new("Focus", None)); cam_d.dof.focus_object = focus

# ---------------------------------------------------------------- render settings
r = scene.render
r.engine = "CYCLES"
r.resolution_x = r.resolution_y = RES
r.resolution_percentage = 100
r.film_transparent = True
scene.cycles.samples = SAMPLES
scene.cycles.use_denoising = True
scene.cycles.denoiser = "OPENIMAGEDENOISE"
scene.cycles.max_bounces = 8
scene.cycles.transmission_bounces = 6
try:
    prefs = bpy.context.preferences.addons["cycles"].preferences
    prefs.compute_device_type = "METAL"
    prefs.get_devices()
    for dev in prefs.devices:
        dev.use = True
    scene.cycles.device = "GPU"
except Exception as e:
    print("GPU setup failed, using CPU:", e)
scene.view_settings.view_transform = "AgX"
try:
    scene.view_settings.look = "AgX - Medium High Contrast"
except Exception:
    pass
r.image_settings.file_format = "PNG"
r.image_settings.color_mode = "RGBA"
r.image_settings.compression = 15

# ---------------------------------------------------------------- rig (float + rotate)
PIVOT = Vector((0.03, 0.0, 0.11))
rig = link_obj(bpy.data.objects.new("Rig", None)); rig.location = PIVOT
RIG_INV = Matrix.Translation(PIVOT).inverted()
SKIP = {"Camera", "Focus", "Rig"}
def attach(ob):
    ob.parent = rig
    ob.matrix_parent_inverse = RIG_INV
for ob in list(scene.objects):
    if ob.name in SKIP or ob.type == "LIGHT" or ob.parent is not None:
        continue
    attach(ob)
def rig_pose(t):
    e = ease_in_out(t)
    rig.rotation_mode = "XYZ"
    rig.rotation_euler = (math.radians(lerp(-10, 6, e)),    # tilt toward / away from camera
                          math.radians(lerp(-5, 5, e)),     # roll
                          math.radians(lerp(-28, 22, e)))   # turn

# ---------------------------------------------------------------- render loop
for f in FRAMES:
    t = f / (COUNT - 1)
    P, d, perp, h = place_sticks(t)
    build_lift(t)
    attach(lift_obj)
    rig_pose(t)
    # focus slides from the noodle nest to the lifted tangle
    focus.location = Vector((P.x, P.y - 0.02, lerp(BROTH_Z + 0.01, P.z - 0.05, h)))
    r.filepath = os.path.join(OUT, f"frame_{f:03d}.png")
    bpy.ops.render.render(write_still=True)
    print(f"rendered {f}", flush=True)

if SAVE:
    bpy.ops.wm.save_as_mainfile(filepath=os.path.join(HERE, "noodle_bowl.blend"))
