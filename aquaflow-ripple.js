/** AquaFlow's rising drop, nested currents, spray, and a wave that answers a hand. */
const {
  Cam, clamp, disposer, fillet, fit, mk, open, place, pointer, poly, proj,
  register, spring, stepS,
} = HL;

const BLADE_OUTLINES = [
  [[-34, 16], [-46, 45], [-39, 72], [-18, 103], [-7, 139], [0, 154], [-2, 129], [18, 100], [33, 70], [30, 39], [14, 17], [-9, 9]],
  [[-23, 17], [-31, 40], [-25, 58], [-5, 78], [5, 100], [6, 119], [21, 88], [23, 61], [10, 39], [-4, 27]],
  [[-12, 17], [-18, 34], [-11, 47], [2, 53], [8, 65], [5, 76], [18, 60], [18, 40], [7, 25]],
];
const SPRAY = [[29, 120, 3.4], [38, 108, 2.8], [44, 96, 2.4]];

function mount({ stage, svg, read }, value) {
  const bag = disposer();
  const camera = Cam(45, 0.5, 1.42);
  fit(camera, [[-76, -13, 0], [76, 22, 0], [-48, -13, 159], [48, 22, 159]], 200, 166);
  const P = proj(camera);
  const group = mk("g", {}, svg);
  const blades = BLADE_OUTLINES.map((outline, index) => {
    const body = mk("path", { class: index === 0 ? "sil hi" : "sil" }, group);
    const crease = mk("path", { class: "nf lo" }, group);
    return { outline: fillet(outline, outline.map(() => 2.5), 4), body, crease, depth: -8 + index * 7 };
  });
  const droplets = SPRAY.map(([, , radius]) => mk("circle", { r: radius, class: "dot m" }, group));
  const waveBack = mk("path", { class: "sil" }, group);
  const waveFace = mk("path", { class: "sil" }, group);
  const waveCrease = mk("path", { class: "nf lo" }, group);
  const waveTip = mk("path", { class: "nf lo" }, group);
  const crest = Array.from({ length: 9 }, () => spring(0, { eps: 0.035 }));
  const lift = spring(0, { eps: 0.035 });
  let amplitude = value, contact = null, lastDraw = "";

  function waveHeight(x) {
    const q = clamp((x + 72) / 144, 0, 1) * 8;
    const i = Math.min(7, Math.floor(q)), t = q - i;
    return crest[i].x * (1 - t) + crest[i + 1].x * t;
  }

  function draw() {
    const state = crest.map((part) => part.x.toFixed(2)).join(",") + ":" + lift.x.toFixed(2);
    if (state === lastDraw) return;
    lastDraw = state;
    blades.forEach(({ outline, body, crease, depth }, index) => {
      const rise = lift.x * (1 - index * 0.22);
      const front = outline.map(([x, z]) => P(x, depth + 3, z + rise));
      const rear = outline.map(([x, z]) => P(x + 2, depth - 3, z + rise + 1));
      body.setAttribute("d", poly(rear) + poly(front));
      crease.setAttribute("d", open(outline.slice(2, 8).map(([x, z]) => P(x + 3, depth + 3, z + rise - 2))));
    });
    droplets.forEach((dot, index) => {
      const [x, z] = SPRAY[index];
      place(dot, P(x, -2, z + lift.x * (1 - index * 0.18)));
    });
    const upper = [], lower = [], back = [];
    for (let i = 0; i <= 24; i++) {
      const x = -72 + i * 6;
      const taper = Math.sin(Math.PI * i / 24) ** 0.65;
      const base = 12 + 5 * Math.sin((i / 24) * Math.PI * 1.7 - 0.6);
      const swell = waveHeight(x) * taper;
      upper.push(P(x, 13, base + 8 * taper + swell));
      lower.push(P(x, 13, base - 7 * taper + swell * 0.3));
      back.push(P(x, 5, base + 8 * taper + swell));
    }
    waveBack.setAttribute("d", poly([...back, ...upper.slice().reverse()]));
    waveFace.setAttribute("d", poly([...upper, ...lower.slice().reverse()]));
    waveCrease.setAttribute("d", open(upper.slice(2, -2).map(([x, y]) => [x, y + 4])));
    waveTip.setAttribute("d", open(upper.slice(17, 24)));
  }

  const loop = register(stage, (dt) => {
    let moving = stepS(lift, dt);
    for (const part of crest) moving = stepS(part, dt) || moving;
    draw();
    return moving;
  });
  bag.add(loop.unregister);

  function retarget() {
    crest.forEach((part, index) => {
      const x = -72 + index * 18;
      part.t = contact === null ? 0 : amplitude * Math.exp(-(((x - contact) / 29) ** 2));
    });
    lift.t = contact === null ? 0 : Math.min(12, amplitude * 0.31);
    waveFace.classList.toggle("hi", contact !== null);
    blades[0].body.classList.toggle("hi", contact === null);
    read.textContent = contact === null ? "rest" : "ripple";
    loop.wake();
  }

  bag.add(pointer(stage, {
    move: ([x, y]) => {
      const center = P(0, 13, 15);
      if (Math.abs(y - center[1]) > 70 || Math.abs(x - center[0]) > 135) {
        if (contact !== null) { contact = null; retarget(); }
        return;
      }
      const left = P(-72, 13, 15)[0], right = P(72, 13, 15)[0];
      contact = clamp(-72 + (x - left) * 144 / (right - left), -72, 72);
      retarget();
    },
    leave: () => { contact = null; retarget(); },
  }));
  bag.add(() => svg.replaceChildren());
  read.textContent = "rest";
  draw();

  return { set: (next) => { amplitude = next; if (contact !== null) retarget(); }, destroy: bag.dispose };
}

hairline({
  name: "aquaflow-ripple",
  means: "A rising water drop carries a ripple from the wave beneath it when the pointer passes over.",
  rules: [1, 3, 4, 5, 7],
  range: [7, 17, 29],
  tour: [[125, 218], [196, 231], [272, 218], null],
  mount,
});
