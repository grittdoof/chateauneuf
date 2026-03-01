/**
 * api/admin-ideas.js
 * Vercel Serverless Function — Gestion des idées citoyennes (admin)
 * GET  → Lister toutes les idées
 * PATCH → Marquer comme lu/non-lu
 */

import { verifyToken } from './admin-login.js';
import { getSupabase } from './_supabase.js';

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, PATCH, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(200).end();

  // Vérifier le token
  const authHeader = req.headers.authorization || '';
  const token = authHeader.replace('Bearer ', '');
  if (!verifyToken(token)) {
    return res.status(401).json({ success: false, message: 'Non autorisé.' });
  }

  const supabase = getSupabase();

  // ── GET : lister les idées ─────────────────────────────────────────────────
  if (req.method === 'GET') {
    try {
      const { data, error } = await supabase
        .from('ideas')
        .select('*')
        .order('created_at', { ascending: false });

      if (error) throw error;

      const ideas = data || [];
      return res.status(200).json({
        success: true,
        ideas,
        total: ideas.length,
        unread: ideas.filter(i => !i.is_read).length,
      });
    } catch (err) {
      console.error('[ADMIN IDEAS ERROR]', err.message);
      return res.status(500).json({ success: false, message: 'Erreur de chargement des idées.' });
    }
  }

  // ── PATCH : marquer lu/non-lu ──────────────────────────────────────────────
  if (req.method === 'PATCH') {
    const { id, is_read } = req.body || {};

    if (!id) {
      return res.status(400).json({ success: false, message: 'ID requis.' });
    }

    try {
      const { error } = await supabase
        .from('ideas')
        .update({ is_read: !!is_read })
        .eq('id', id);

      if (error) throw error;

      return res.status(200).json({ success: true });
    } catch (err) {
      console.error('[ADMIN IDEAS PATCH ERROR]', err.message);
      return res.status(500).json({ success: false, message: 'Erreur de mise à jour.' });
    }
  }

  return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
}
