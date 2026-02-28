/**
 * api/admin-login.js
 * Vercel Serverless Function — Authentification admin
 * Vérifie le mot de passe et retourne un token JWT-like (HMAC)
 */

import bcrypt from 'bcryptjs';
import crypto from 'crypto';

const TOKEN_LIFETIME = 3600; // 1 heure

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
  }

  const { password } = req.body || {};
  if (!password) {
    return res.status(400).json({ success: false, message: 'Mot de passe requis.' });
  }

  const hash = process.env.ADMIN_PASSWORD_HASH || '';
  if (!hash) {
    return res.status(500).json({ success: false, message: 'Configuration admin manquante.' });
  }

  const valid = await bcrypt.compare(password, hash);
  if (!valid) {
    return res.status(401).json({ success: false, message: 'Mot de passe incorrect.' });
  }

  // Créer un token signé (expiry.signature)
  const expiry = Math.floor(Date.now() / 1000) + TOKEN_LIFETIME;
  const signature = crypto
    .createHmac('sha256', hash)
    .update(String(expiry))
    .digest('hex');

  return res.status(200).json({
    success: true,
    token: `${expiry}.${signature}`,
    expiresIn: TOKEN_LIFETIME,
  });
}

/**
 * Vérifie un token admin (utilisable par d'autres endpoints)
 */
export function verifyToken(token) {
  if (!token) return false;

  const hash = process.env.ADMIN_PASSWORD_HASH || '';
  if (!hash) return false;

  const parts = token.split('.');
  if (parts.length !== 2) return false;

  const [expiryStr, sig] = parts;
  const expiry = parseInt(expiryStr, 10);

  // Token expiré ?
  if (expiry < Math.floor(Date.now() / 1000)) return false;

  // Signature valide ?
  const expected = crypto
    .createHmac('sha256', hash)
    .update(expiryStr)
    .digest('hex');

  return crypto.timingSafeEqual(Buffer.from(sig), Buffer.from(expected));
}
