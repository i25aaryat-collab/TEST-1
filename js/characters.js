/* ==========================================================================
 * js/characters.js  —  Owner: M1 (Lead)
 * --------------------------------------------------------------------------
 * The 12 RoomieMatch characters, drawn as SVG in code (no image files).
 *   - On the landing page their eyes and heads follow the mouse cursor.
 *   - The same drawings are saved as avatar icons (css/avatars/c01.svg ...)
 *     that users can pick instead of uploading a photo.
 *
 * rmCharacterSVG(character, look) returns an SVG string.
 * look = { x, y } from -1 to 1: where the character is looking.
 * ========================================================================== */

const RM_CHARACTERS = [
    { id: 'c01', name: 'Sunny',  skin: '#F3C7A1', hoodie: '#F2B705', hat: 'beanie',    hatColor: '#F2B705', hair: '#4A3222', mouth: 'grin',  bg: '#FFE6A8' },
    { id: 'c02', name: 'Kabir',  skin: '#C98E62', hoodie: '#2B2D42', hat: 'beanie',    hatColor: '#23242F', hair: '#1D1612', mouth: 'flat',  bg: '#D9DBE8', glasses: true },
    { id: 'c03', name: 'Meher',  skin: '#F6D2B8', hoodie: '#FF5A5F', hat: 'bun',       hatColor: '#2F1F18', hair: '#2F1F18', mouth: 'smile', bg: '#FFD5D7' },
    { id: 'c04', name: 'Dev',    skin: '#8D5A3B', hoodie: '#2E86DE', hat: 'cap',       hatColor: '#1F6FB2', hair: '#140F0C', mouth: 'grin',  bg: '#CFE6FF' },
    { id: 'c05', name: 'Tara',   skin: '#E9B48C', hoodie: '#2BB673', hat: 'curls',     hatColor: '#5B3A26', hair: '#5B3A26', mouth: 'o',     bg: '#CDEFD9' },
    { id: 'c06', name: 'Aman',   skin: '#F1C29C', hoodie: '#E1E4EA', hat: 'straw',     hatColor: '#E8C46B', hair: '#3A2A1E', mouth: 'grin',  bg: '#F6EBD0' },
    { id: 'c07', name: 'Zoya',   skin: '#B97A52', hoodie: '#7A4DD8', hat: 'scarf',     hatColor: '#2B9C9C', hair: '#1D1410', mouth: 'smile', bg: '#D3F0EE' },
    { id: 'c08', name: 'Rishi',  skin: '#D9A07A', hoodie: '#1E8A5B', hat: 'capback',   hatColor: '#1E8A5B', hair: '#2A1C14', mouth: 'o',     bg: '#D6F2E2' },
    { id: 'c09', name: 'Nisha',  skin: '#F7D7C0', hoodie: '#F08A24', hat: 'headphones',hatColor: '#2B2D42', hair: '#7A4A2A', mouth: 'smile', bg: '#FFE0C2' },
    { id: 'c10', name: 'Omar',   skin: '#6E4630', hoodie: '#D94F30', hat: 'beanie',    hatColor: '#2FB5D6', hair: '#120D0A', mouth: 'grin',  bg: '#CFEFF7' },
    { id: 'c11', name: 'Lina',   skin: '#F4CDB0', hoodie: '#C7D2FE', hat: 'long',      hatColor: '#E9C46A', hair: '#E9C46A', mouth: 'smile', bg: '#E4E8FF', glasses: true },
    { id: 'c12', name: 'Vik',    skin: '#E2A97E', hoodie: '#8B5E3C', hat: 'knit',      hatColor: '#ECE7E1', hair: '#2BB673', mouth: 'flat',  bg: '#EFE9E2' },
];

/** Darken or lighten a hex colour by amount (-1..1) for simple shading */
function rmShade(hex, amount) {
    const n = parseInt(hex.slice(1), 16);
    const mix = (c) => Math.round(amount < 0 ? c * (1 + amount) : c + (255 - c) * amount);
    const r = mix(n >> 16), g = mix((n >> 8) & 255), b = mix(n & 255);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

/**
 * Hair or headwear. Returns { back, front }: "back" is drawn behind the head
 * (long hair, a headscarf), "front" on top of it (hats, fringes).
 */
function rmHat(c) {
    const h = c.hatColor, d = rmShade(h, -0.25), l = rmShade(h, 0.25);
    switch (c.hat) {
        case 'beanie':
            return { back: '', front: `<path d="M46 66 Q46 16 100 14 Q154 16 154 66 Z" fill="${h}"/>
                    <rect x="42" y="54" width="116" height="20" rx="10" fill="${d}"/>
                    <path d="M64 34 Q100 22 136 34" stroke="${l}" stroke-width="4" fill="none" opacity=".5"/>` };
        case 'knit':
            return { back: '', front: `<path d="M44 74 Q44 84 52 88 L52 66 Z" fill="${c.hair}"/><path d="M156 74 Q156 84 148 88 L148 66 Z" fill="${c.hair}"/>
                    <path d="M46 64 Q46 12 100 10 Q154 12 154 64 Z" fill="${h}"/>
                    <circle cx="100" cy="9" r="12" fill="${l}"/>
                    <rect x="42" y="52" width="116" height="20" rx="10" fill="${d}"/>
                    <path d="M72 24 v28 M100 18 v34 M128 24 v28" stroke="${d}" stroke-width="3" opacity=".45"/>` };
        case 'cap':
            return { back: '', front: `<path d="M48 66 Q48 20 100 20 Q152 20 152 66 Z" fill="${h}"/>
                    <path d="M48 62 Q100 56 174 68 Q178 76 162 76 Q100 68 48 72 Z" fill="${d}"/>
                    <circle cx="100" cy="22" r="5" fill="${d}"/>` };
        case 'capback':
            return { back: '', front: `<path d="M48 68 Q48 20 100 20 Q152 20 152 68 Z" fill="${h}"/>
                    <path d="M38 62 Q48 54 72 60 L68 70 Q50 68 38 70 Z" fill="${d}"/>
                    <rect x="88" y="50" width="24" height="10" rx="5" fill="${l}"/>` };
        case 'straw':
            return { back: '', front: `<ellipse cx="100" cy="56" rx="82" ry="17" fill="${h}"/>
                    <path d="M56 54 Q58 14 100 14 Q142 14 144 54 Z" fill="${l}"/>
                    <rect x="56" y="40" width="88" height="11" fill="#D94F30"/>` };
        case 'bun':
            return { back: '', front: `<circle cx="100" cy="20" r="20" fill="${c.hair}"/>
                    <path d="M44 92 Q40 34 100 32 Q160 34 156 92 Q150 58 100 54 Q50 58 44 92 Z" fill="${c.hair}"/>` };
        case 'curls': {
            let s = '';
            [[54,60],[66,42],[84,32],[104,28],[124,32],[142,44],[152,64],[48,80]].forEach(([x, y]) => { s += `<circle cx="${x}" cy="${y}" r="16" fill="${c.hair}"/>`; });
            return { back: '', front: s };
        }
        case 'long':
            return { back: `<path d="M40 100 L36 158 L164 158 L160 100 Z" fill="${c.hair}"/>`,
                     front: `<path d="M42 104 Q36 28 100 28 Q164 28 158 104 Q150 66 118 56 Q96 74 58 70 Q48 84 46 104 Z" fill="${c.hair}"/>` };
        case 'scarf':
            return { back: `<path d="M34 106 Q30 22 100 20 Q170 22 166 106 L172 164 L28 164 Z" fill="${h}"/>`,
                     front: `<path d="M44 92 Q44 34 100 32 Q156 34 156 92 Q150 54 100 50 Q50 54 44 92 Z" fill="${h}"/>
                             <path d="M50 70 Q100 44 150 70" stroke="${d}" stroke-width="3" fill="none" opacity=".5"/>` };
        case 'headphones':
            return { back: '', front: `<path d="M46 88 Q42 32 100 30 Q158 32 154 88 Q146 56 100 54 Q54 56 46 88 Z" fill="${c.hair}"/>
                    <path d="M38 96 Q38 18 100 18 Q162 18 162 96" stroke="${h}" stroke-width="9" fill="none"/>
                    <rect x="28" y="82" width="22" height="36" rx="10" fill="${h}"/><rect x="150" y="82" width="22" height="36" rx="10" fill="${h}"/>` };
        default:
            return { back: '', front: '' };
    }
}

/** Mouth shapes */
function rmMouth(c) {
    const lip = rmShade(c.skin, -0.45);
    switch (c.mouth) {
        case 'grin':  return `<path d="M84 128 Q100 146 116 128 Z" fill="#fff" stroke="${lip}" stroke-width="3" stroke-linejoin="round"/>`;
        case 'o':     return `<ellipse cx="100" cy="132" rx="6" ry="8" fill="${lip}"/>`;
        case 'flat':  return `<path d="M88 132 Q100 136 112 131" stroke="${lip}" stroke-width="4" fill="none" stroke-linecap="round"/>`;
        default:      return `<path d="M86 128 Q100 140 114 128" stroke="${lip}" stroke-width="4" fill="none" stroke-linecap="round"/>`;
    }
}

/**
 * Build one character as an SVG string.
 * look.x / look.y (-1..1) move the pupils, slide the face and tilt the head,
 * which together make the character look towards that point.
 */
function rmCharacterSVG(c, look = { x: 0, y: 0 }, withBackground = true) {
    const lx = Math.max(-1, Math.min(1, look.x)), ly = Math.max(-1, Math.min(1, look.y));
    const skinShadow = rmShade(c.skin, -0.18), hoodieDark = rmShade(c.hoodie, -0.22);
    const uid = c.id;
    const hat = rmHat(c);
    const face = `translate(${(lx * 9).toFixed(2)} ${(ly * 6).toFixed(2)})`;
    const head = `translate(${(lx * 4).toFixed(2)} ${(ly * 3).toFixed(2)}) rotate(${(lx * 5).toFixed(2)} 100 150)`;
    const px = (lx * 6).toFixed(2), py = (ly * 6).toFixed(2);
    const eyes = `<ellipse cx="80" cy="100" rx="13" ry="15" fill="#fff"/><ellipse cx="120" cy="100" rx="13" ry="15" fill="#fff"/>
        <g class="rm-eyes" transform="translate(${px} ${py})">
          <circle cx="80" cy="100" r="6.5" fill="#1E1F2B"/><circle cx="82.5" cy="97.5" r="2" fill="#fff"/>
          <circle cx="120" cy="100" r="6.5" fill="#1E1F2B"/><circle cx="122.5" cy="97.5" r="2" fill="#fff"/>
        </g>`;
    const glasses = c.glasses ? `<g fill="none" stroke="#1E1F2B" stroke-width="4"><circle cx="80" cy="100" r="17"/><circle cx="120" cy="100" r="17"/><path d="M97 100 h6"/></g>` : '';

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img" aria-label="${c.name}">
  <defs>
    <radialGradient id="g${uid}" cx="40%" cy="35%" r="70%"><stop offset="0" stop-color="#fff" stop-opacity=".35"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
    <clipPath id="k${uid}"><circle cx="100" cy="100" r="100"/></clipPath>
  </defs>
  <g clip-path="${withBackground ? `url(#k${uid})` : 'none'}">
    ${withBackground ? `<rect width="200" height="200" fill="${c.bg}"/>` : ''}
    <path d="M24 210 Q26 162 100 158 Q174 162 176 210 Z" fill="${c.hoodie}"/>
    <path d="M70 160 Q100 182 130 160" stroke="${hoodieDark}" stroke-width="6" fill="none"/>
    <path d="M90 172 v22 M110 172 v22" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".8"/>
    <rect x="86" y="140" width="28" height="24" rx="8" fill="${skinShadow}"/>
    <g class="rm-head" transform="${head}">
      ${hat.back}
      <ellipse cx="46" cy="104" rx="10" ry="13" fill="${skinShadow}"/><ellipse cx="154" cy="104" rx="10" ry="13" fill="${skinShadow}"/>
      <ellipse cx="100" cy="98" rx="56" ry="58" fill="${c.skin}"/>
      <ellipse cx="100" cy="98" rx="56" ry="58" fill="url(#g${uid})"/>
      <g class="rm-face" transform="${face}">
        <ellipse cx="74" cy="118" rx="9" ry="5" fill="#FF8A8A" opacity=".35"/><ellipse cx="126" cy="118" rx="9" ry="5" fill="#FF8A8A" opacity=".35"/>
        ${eyes}
        <path d="M68 80 Q80 74 92 80 M108 80 Q120 74 132 80" stroke="${c.hair}" stroke-width="4" fill="none" stroke-linecap="round"/>
        <ellipse cx="100" cy="116" rx="5" ry="4" fill="${skinShadow}"/>
        ${rmMouth(c)}
        ${glasses}
      </g>
      ${hat.front}
    </g>
  </g>
</svg>`;
}

/**
 * Point an already-drawn character (an <svg> element) at look.x / look.y
 * without redrawing it: only three transforms change, so it is cheap
 * enough to run on every mouse move.
 */
function rmLook(svg, x, y) {
    const lx = Math.max(-1, Math.min(1, x)), ly = Math.max(-1, Math.min(1, y));
    svg.querySelector('.rm-head').setAttribute('transform', `translate(${(lx * 4).toFixed(2)} ${(ly * 3).toFixed(2)}) rotate(${(lx * 5).toFixed(2)} 100 150)`);
    svg.querySelector('.rm-face').setAttribute('transform', `translate(${(lx * 9).toFixed(2)} ${(ly * 6).toFixed(2)})`);
    svg.querySelector('.rm-eyes').setAttribute('transform', `translate(${(lx * 6).toFixed(2)} ${(ly * 6).toFixed(2)})`);
}

// Let Node (the avatar generator script) use these functions too
if (typeof module !== 'undefined') {
    module.exports = { RM_CHARACTERS, rmCharacterSVG, rmLook };
}
