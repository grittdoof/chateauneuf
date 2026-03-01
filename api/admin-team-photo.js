/**
 * api/admin-team-photo.js
 * Vercel Serverless Function — Upload photo d'un membre (admin)
 * POST → Reçoit une image base64 optimisée côté client, stocke dans Supabase Storage
 */

import { verifyToken } from './admin-login.js';
import { getSupabase } from './_supabase.js';

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(200).end();

  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
  }

  // Vérifier le token
  const authHeader = req.headers.authorization || '';
  const token = authHeader.replace('Bearer ', '');
  if (!verifyToken(token)) {
    return res.status(401).json({ success: false, message: 'Non autorisé.' });
  }

  const { memberId, imageData, filename } = req.body || {};

  // ── Validation ──────────────────────────────────────────────────────────────
  if (!memberId || !imageData || !filename) {
    return res.status(400).json({ success: false, message: 'Données manquantes (memberId, imageData, filename).' });
  }

  // Extraire le contenu base64 (strip le préfixe data URI)
  const base64Match = imageData.match(/^data:image\/(jpeg|png|webp);base64,(.+)$/);
  if (!base64Match) {
    return res.status(400).json({ success: false, message: 'Format image invalide. Accepté : JPEG, PNG, WebP.' });
  }

  const mimeType = 'image/' + base64Match[1];
  const buffer = Buffer.from(base64Match[2], 'base64');

  // Vérifier la taille (max 500KB après optimisation client)
  if (buffer.length > 500 * 1024) {
    return res.status(400).json({ success: false, message: 'Image trop volumineuse (max 500 Ko après optimisation).' });
  }

  // Nettoyer le nom de fichier
  const cleanFilename = filename.replace(/[^a-z0-9\-_.]/gi, '').toLowerCase();
  if (!cleanFilename) {
    return res.status(400).json({ success: false, message: 'Nom de fichier invalide.' });
  }

  const supabase = getSupabase();

  try {
    // ── Upload vers Supabase Storage ────────────────────────────────────────
    const { data: uploadData, error: uploadError } = await supabase.storage
      .from('team-photos')
      .upload(cleanFilename, buffer, {
        contentType: mimeType,
        upsert: true,
        cacheControl: '2592000', // 30 jours
      });

    if (uploadError) {
      console.error('[PHOTO UPLOAD ERROR]', uploadError.message);
      return res.status(500).json({ success: false, message: 'Erreur lors de l\'upload de la photo.' });
    }

    // ── Récupérer l'URL publique ────────────────────────────────────────────
    const { data: urlData } = supabase.storage
      .from('team-photos')
      .getPublicUrl(cleanFilename);

    const publicUrl = urlData.publicUrl;

    // ── Mettre à jour le champ photo du membre ─────────────────────────────
    const { error: updateError } = await supabase
      .from('team_members')
      .update({ photo: publicUrl, updated_at: new Date().toISOString() })
      .eq('id', memberId);

    if (updateError) {
      console.error('[PHOTO DB UPDATE ERROR]', updateError.message);
      // La photo est uploadée mais la BDD n'est pas mise à jour
      // On retourne quand même l'URL pour que le client puisse réessayer
    }

    return res.status(200).json({ success: true, url: publicUrl });

  } catch (err) {
    console.error('[PHOTO UPLOAD ERROR]', err.message);
    return res.status(500).json({ success: false, message: 'Erreur lors du traitement de la photo.' });
  }
}
