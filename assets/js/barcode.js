/* Minimal Code 128B barcode renderer — no external dependencies */
/* MIT-compatible implementation for WC Fulfillment SL */
(function (global) {
	'use strict';

	// Code 128 symbol patterns (11 modules each, index 0–102 = data, 103=StartA, 104=StartB, 105=StartC)
	// Index 106 = Stop (13 modules). Bars are 1, spaces are 0.
	var P = [
		'11011001100','11001101100','11001100110','10010011000','10010001100',
		'10001001100','10011001000','10011000100','10001100100','11001001000',
		'11001000100','11000100100','10110011100','10011011100','10011001110',
		'10111001100','10011101100','10011100110','11001110010','11001011100',
		'11001001110','11011100100','11001110100','11101101110','11101001100',
		'11100101100','11100100110','11101100100','11100110100','11100110010',
		'11011011000','11011000110','11000110110','10100011000','10001011000',
		'10001000110','10110001000','10001101000','10001100010','11010001000',
		'11000101000','11000100010','10110111000','10110001110','10001101110',
		'10111011000','10111000110','10001110110','11101110110','11010001110',
		'11000101110','11011101000','11011100010','11011101110','11101011000',
		'11101000110','11100010110','11101101000','11101100010','11100011010',
		'11101111010','11001000010','11110001010','10100110000','10100001100',
		'10010110000','10010000110','10000101100','10000100110','10110010000',
		'10110000100','10011010000','10011000010','10000110100','10000110010',
		'11000010010','11001010000','11110111010','11000010100','10001111010',
		'10100111100','10010111100','10010011110','10111100100','10011110100',
		'10011110010','11110100100','11110010100','11110010010','11011011110',
		'11011110110','11110110110','10101111000','10100011110','10001011110',
		'10111101000','10111100010','11110101000','11110100010','10111011110',
		'10111101110','11101011110','11110101110',
		// 103 = Start A, 104 = Start B, 105 = Start C
		'11010000100','11010010000','11010011110',
		// 106 = Stop
		'1100011101011'
	];

	function encode128B(text) {
		var codes = [104]; // Start B
		for (var i = 0; i < text.length; i++) {
			var c = text.charCodeAt(i) - 32;
			// Code 128B supports ASCII 32–127
			if (c < 0 || c > 95) c = 0; // replace unsupported with space
			codes.push(c);
		}
		// Compute check character
		var check = 104;
		for (var j = 1; j < codes.length; j++) {
			check += j * codes[j];
		}
		codes.push(check % 103);
		// Build bit string: symbols + stop
		var bits = '';
		for (var k = 0; k < codes.length; k++) {
			bits += P[codes[k]];
		}
		bits += P[106]; // Stop
		return bits;
	}

	function draw(canvas, text, opts) {
		if (!canvas || !canvas.getContext) return;
		opts = opts || {};
		var moduleW = opts.moduleWidth || 2;
		var barH    = opts.height      || 80;
		var quiet   = opts.quiet       || 20;

		var bits   = encode128B(String(text).toUpperCase());
		var totalW = bits.length * moduleW + quiet * 2;

		canvas.width  = totalW;
		canvas.height = barH;

		var ctx = canvas.getContext('2d');
		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, totalW, barH);

		ctx.fillStyle = '#000000';
		var x = quiet;
		for (var i = 0; i < bits.length; i++) {
			if (bits[i] === '1') {
				ctx.fillRect(x, 0, moduleW, barH);
			}
			x += moduleW;
		}
	}

	global.WCFSLBarcode = { draw: draw };

}(typeof window !== 'undefined' ? window : this));
