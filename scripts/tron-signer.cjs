#!/usr/bin/env node
'use strict';

/**
 * Tron kripto yardımcısı (secp256k1 + keccak). PHP tarafından çağrılır.
 * PHP'de gmp olmadığı için Tron imzalama burada (Node + elliptic) yapılır.
 *
 * Komutlar:
 *   keygen                      -> {"private_key","address_hex","address_base58"}
 *   address <privkey_hex>       -> {"address_hex","address_base58"}
 *   sign <privkey_hex> <hash_hex> -> {"signature"}  (65 bayt: r||s||v hex)
 */

const EC = require('elliptic').ec;
const { keccak256 } = require('js-sha3');
const crypto = require('crypto');

const ec = new EC('secp256k1');
const B58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

function base58(buf) {
    let zeros = 0;

    while (zeros < buf.length && buf[zeros] === 0) {
zeros++;
}

    const digits = [0];

    for (let i = 0; i < buf.length; i++) {
        let carry = buf[i];

        for (let j = 0; j < digits.length; j++) {
            carry += digits[j] << 8;
            digits[j] = carry % 58;
            carry = (carry / 58) | 0;
        }

        while (carry > 0) {
            digits.push(carry % 58);
            carry = (carry / 58) | 0;
        }
    }

    let out = '1'.repeat(zeros);

    for (let i = digits.length - 1; i >= 0; i--) {
out += B58[digits[i]];
}

    return out;
}

function base58Check(hex21) {
    const payload = Buffer.from(hex21, 'hex');
    const h1 = crypto.createHash('sha256').update(payload).digest();
    const h2 = crypto.createHash('sha256').update(h1).digest();

    return base58(Buffer.concat([payload, h2.subarray(0, 4)]));
}

/** base58 string -> byte dizisi */
function base58Decode(str) {
    const bytes = [0];

    for (const ch of str) {
        let carry = B58.indexOf(ch);

        if (carry < 0) {
throw new Error('gecersiz base58');
}

        for (let j = 0; j < bytes.length; j++) {
            carry += bytes[j] * 58;
            bytes[j] = carry & 0xff;
            carry >>= 8;
        }

        while (carry > 0) {
            bytes.push(carry & 0xff);
            carry >>= 8;
        }
    }

    let zeros = 0;

    while (zeros < str.length && str[zeros] === '1') {
zeros++;
}

    for (let i = 0; i < zeros; i++) {
bytes.push(0);
}

    return Buffer.from(bytes.reverse());
}

/** base58 Tron adresini hex'e çevirir (checksum atılır) -> 41+20 bayt hex */
function toHex(base58Addr) {
    const decoded = base58Decode(base58Addr);
    const payload = decoded.subarray(0, decoded.length - 4); // son 4 checksum

    return { address_hex: payload.toString('hex') };
}

/** privkey hex -> {address_hex (0x41..), address_base58} */
function addressFromPrivate(privHex) {
    const key = ec.keyFromPrivate(privHex, 'hex');
    // Sıkıştırılmamış public key, 0x04 ön ekini at (64 bayt)
    const pub = Buffer.from(key.getPublic(false, 'hex'), 'hex').subarray(1);
    const hash = keccak256(pub); // 32 bayt hex
    const addr20 = hash.slice(-40); // son 20 bayt
    const addressHex = '41' + addr20; // Tron mainnet ön eki

    return { address_hex: addressHex, address_base58: base58Check(addressHex) };
}

function keygen() {
    const key = ec.genKeyPair();
    const priv = key.getPrivate('hex').padStart(64, '0');

    return { private_key: priv, ...addressFromPrivate(priv) };
}

/** txID (raw_data'nın sha256'sı) hash'ini imzala -> 65 bayt r||s||v hex */
function sign(privHex, hashHex) {
    const key = ec.keyFromPrivate(privHex, 'hex');
    const sig = key.sign(hashHex, { canonical: true });
    const r = sig.r.toString('hex').padStart(64, '0');
    const s = sig.s.toString('hex').padStart(64, '0');
    const v = sig.recoveryParam.toString(16).padStart(2, '0');

    return { signature: r + s + v };
}

// Girdi stdin'den JSON olarak okunur (private key argv'de görünmesin).
// { "cmd": "keygen|address|tohex|sign", "priv": "...", "hash": "...", "addr": "..." }
function run(input) {
    switch (input.cmd) {
        case 'keygen': return keygen();
        case 'address': return addressFromPrivate(input.priv);
        case 'tohex': return toHex(input.addr);
        case 'sign': return sign(input.priv, input.hash);
        default: throw new Error('bilinmeyen komut');
    }
}

let raw = '';
process.stdin.on('data', (c) => (raw += c));
process.stdin.on('end', () => {
    try {
        process.stdout.write(JSON.stringify(run(JSON.parse(raw || '{}'))));
    } catch (e) {
        process.stderr.write(String(e && e.message ? e.message : e));
        process.exit(1);
    }
});
