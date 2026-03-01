/**
 * api/team.js
 * Vercel Serverless Function — Membres de l'équipe (public, avec cache)
 */

import { getSupabase } from './_supabase.js';

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.setHeader('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=600');

  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'GET') {
    return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
  }

  try {
    const supabase = getSupabase();
    const { data, error } = await supabase
      .from('team_members')
      .select('*')
      .eq('is_active', true)
      .order('sort_order', { ascending: true })
      .order('id', { ascending: true });

    if (error) throw error;

    return res.status(200).json({ success: true, members: data || [] });
  } catch (err) {
    console.error('[TEAM ERROR]', err.message);
    return res.status(500).json({ success: false, message: 'Erreur de chargement.' });
  }
}
