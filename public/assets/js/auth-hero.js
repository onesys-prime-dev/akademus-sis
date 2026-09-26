/*
 * Animated hero illustration for the auth pages.
 *
 * The artwork is a flat image, so the characters can't be cleanly cut out
 * without tearing the background. Instead a WebGL shader gently warps the
 * pixels around each character (smooth gaussian falloff), which reads as
 * the characters bobbing/floating while the rest of the picture stays put.
 * Falls back to the plain <img> when WebGL or motion isn't available.
 */
(function () {
    'use strict';

    var hero = document.querySelector('.auth__right--hero');
    var img = hero && hero.querySelector('.auth__right--hero--img');
    if (!img) return;

    var canvas = document.createElement('canvas');
    canvas.className = 'auth__right--hero--canvas';
    canvas.setAttribute('aria-hidden', 'true');
    var gl = canvas.getContext('webgl', { antialias: false, alpha: true });
    if (!gl) return;

    // Bumps per illustration (data-hero on the container): centre (c) and
    // sigma (s) in artwork pixels, amplitude (a) in pixels, angular speed (w)
    // and phase (p).
    var PRESETS = {
        login: {
            size: [864, 864],
            bumps: [
                { c: [160, 545], s: [85, 200], a: [1.6, -4.5], w: 2.0, p: 0.0 },  // walker
                { c: [548, 230], s: [80, 115], a: [1.2, -5.5], w: 1.5, p: 1.7 },  // girl on laptop
                { c: [655, 575], s: [85, 115], a: [-1.0, -3.5], w: 1.7, p: 3.1 }, // reader
                { c: [390, 132], s: [30, 24], a: [7, -6], w: 1.3, p: 0.6 }        // paper plane
            ]
        },
        forgot: {
            size: [1060, 880],
            bumps: [
                { c: [390, 610], s: [150, 170], a: [1.0, -4.0], w: 2.0, p: 0.0 }, // person
                { c: [820, 80], s: [60, 45], a: [12, -9], w: 1.3, p: 0.6 },       // paper plane
                { c: [308, 270], s: [70, 80], a: [2, -6], w: 1.6, p: 1.0 },       // sticky note
                { c: [955, 400], s: [70, 80], a: [2, -7], w: 1.4, p: 2.5 },       // speech bubble
                { c: [730, 770], s: [110, 55], a: [0, -3], w: 1.1, p: 0.5 },      // sleeping cat
                { c: [630, 350], s: [75, 75], a: [0, -3], w: 1.8, p: 1.2 },       // lock
                { c: [160, 560], s: [55, 100], a: [3, -1.5], w: 1.2, p: 0.3 },    // plant left
                { c: [930, 620], s: [60, 110], a: [-3, -1.5], w: 1.1, p: 2.0 }    // plant right
            ]
        },
        // Artwork is 1672px wide but shown ~560px wide, so amplitudes are
        // larger than the other presets to read as the same gentle motion.
        reset: {
            size: [1672, 941],
            bumps: [
                { c: [250, 540], s: [120, 260], a: [5, -16], w: 2.0, p: 0.0 },   // person
                { c: [390, 240], s: [60, 60], a: [3, -20], w: 1.6, p: 0.8 },     // shield badge
                { c: [735, 335], s: [75, 75], a: [0, -14], w: 1.8, p: 1.2 },     // lock
                { c: [1165, 150], s: [75, 55], a: [32, -22], w: 1.3, p: 0.6 },   // paper plane
                { c: [1085, 650], s: [95, 135], a: [-9, -5], w: 1.1, p: 2.0 },   // plant
                { c: [875, 145], s: [110, 35], a: [0, -14], w: 2.2, p: 0.3 },    // sparkles top
                { c: [1055, 410], s: [40, 40], a: [0, -14], w: 1.9, p: 1.5 },    // sparkle right
                { c: [78, 297], s: [38, 38], a: [0, -14], w: 2.0, p: 0.4 }       // sparkle left
            ]
        }
    };
    var preset = PRESETS[hero.getAttribute('data-hero') || 'login'];
    if (!preset) return;
    var bumps = preset.bumps;
    var N = 8;
    while (bumps.length < N) bumps = bumps.concat([{ c: [0, 0], s: [1, 1], a: [0, 0], w: 0, p: 0 }]);
    var SW = preset.size[0], SH = preset.size[1];

    var vs =
        'attribute vec2 p; varying vec2 uv;' +
        'void main(){ uv = vec2(p.x * .5 + .5, .5 - p.y * .5); gl_Position = vec4(p, 0., 1.); }';

    var fs =
        'precision mediump float; varying vec2 uv; uniform sampler2D tex; uniform float t;' +
        'uniform vec2 c[8]; uniform vec2 s[8]; uniform vec2 a[8]; uniform vec2 wp[8];' +
        'void main(){' +
        '  vec2 sz = vec2(' + SW.toFixed(1) + ', ' + SH.toFixed(1) + ');' +
        '  vec2 px = uv * sz; vec2 d = vec2(0.);' +
        '  for (int i = 0; i < 8; i++) {' +
        '    vec2 q = (px - c[i]) / s[i];' +
        '    float g = exp(-dot(q, q));' +
        '    float k = sin(t * wp[i].x + wp[i].y);' +
        '    d += a[i] * g * k;' +
        '  }' +
        '  gl_FragColor = texture2D(tex, (px - d) / sz);' +
        '}';

    function shader(type, src) {
        var sh = gl.createShader(type);
        gl.shaderSource(sh, src);
        gl.compileShader(sh);
        return gl.getShaderParameter(sh, gl.COMPILE_STATUS) ? sh : null;
    }

    var v = shader(gl.VERTEX_SHADER, vs);
    var f = shader(gl.FRAGMENT_SHADER, fs);
    if (!v || !f) return;
    var prog = gl.createProgram();
    gl.attachShader(prog, v);
    gl.attachShader(prog, f);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return;
    gl.useProgram(prog);

    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    var loc = gl.getAttribLocation(prog, 'p');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    function flat(key, fn) {
        var out = [];
        bumps.forEach(function (b) { out.push.apply(out, fn(b)); });
        gl.uniform2fv(gl.getUniformLocation(prog, key), new Float32Array(out));
    }
    flat('c', function (b) { return b.c; });
    flat('s', function (b) { return b.s; });
    flat('a', function (b) { return b.a; });
    flat('wp', function (b) { return [b.w, b.p]; });
    var tLoc = gl.getUniformLocation(prog, 't');

    function resize() {
        var k = Math.min(window.devicePixelRatio || 1, 2);
        var w = Math.round(hero.clientWidth * k), h = Math.round(hero.clientHeight * k);
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w;
            canvas.height = h;
            gl.viewport(0, 0, w, h);
        }
    }

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function start() {
        var tex = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, tex);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);

        resize();
        hero.appendChild(canvas);
        img.style.visibility = 'hidden';
        window.addEventListener('resize', function () { resize(); draw(performance.now()); });

        function draw(now) {
            gl.uniform1f(tLoc, reduce ? 0 : now / 1000);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        }
        function frame(now) {
            draw(now);
            requestAnimationFrame(frame);
        }
        if (reduce) draw(0); else requestAnimationFrame(frame);
    }

    if (img.complete && img.naturalWidth) start();
    else img.addEventListener('load', start);
})();
