/**
 * api/admin-contacts.js
 * Vercel Serverless Function — Récupérer les inscriptions depuis Supabase
 */

import { verifyToken } from './admin-login.js';
import { getSupabase } from './_supabase.js';

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'GET') {
    return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
  }

  // Vérifier le token
  const authHeader = req.headers.authorization || '';
  const token = authHeader.replace('Bearer ', '');
  if (!verifyToken(token)) {
    return res.status(401).json({ success: false, message: 'Non autorisé.' });
  }

  try {
    const supabase = getSupabase();
    const { data, error, count } = await supabase
      .from('subscriptions')
      .select('*', { count: 'exact' })
      .order('created_at', { ascending: false });

    if (error) throw error;

    const contacts = (data || []).map(c => ({
      id: c.id,
      prenom: c.prenom,
      nom: c.nom,
      email: c.email,
      telephone: c.telephone,
      date: c.created_at,
    }));

    return res.status(200).json({
      success: true,
      contacts,
      total: count || contacts.length,
    });
  } catch (err) {
    console.error('[ADMIN CONTACTS ERROR]', err.message);
    return res.status(500).json({
      success: false,
      message: 'Erreur lors de la récupération des inscriptions.',
    });
  }
}
