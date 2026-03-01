/**
 * api/admin-team.js
 * Vercel Serverless Function — Gestion des membres de l'équipe (admin)
 * GET    → Lister tous les membres (y compris inactifs)
 * POST   → Ajouter un nouveau membre
 * PATCH  → Modifier un membre OU réordonner en batch
 * DELETE → Activer/désactiver un membre (soft delete)
 */

import { verifyToken } from './admin-login.js';
import { getSupabase } from './_supabase.js';

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(200).end();

  // Vérifier le token
  const authHeader = req.headers.authorization || '';
  const token = authHeader.replace('Bearer ', '');
  if (!verifyToken(token)) {
    return res.status(401).json({ success: false, message: 'Non autorisé.' });
  }

  const supabase = getSupabase();

  // ── GET : lister tous les membres ──────────────────────────────────────────
  if (req.method === 'GET') {
    try {
      const { data, error } = await supabase
        .from('team_members')
        .select('*')
        .order('sort_order', { ascending: true })
        .order('id', { ascending: true });

      if (error) throw error;

      const members = data || [];
      return res.status(200).json({
        success: true,
        members,
        total: members.length,
        active: members.filter(m => m.is_active).length,
      });
    } catch (err) {
      console.error('[ADMIN TEAM GET ERROR]', err.message);
      return res.status(500).json({ success: false, message: 'Erreur de chargement.' });
    }
  }

  // ── POST : créer un nouveau membre ─────────────────────────────────────────
  if (req.method === 'POST') {
    const { prenom, nom, role, age, profession, photo, depuis, q1, q2, q3, sort_order, is_active } = req.body || {};

    if (!prenom?.trim() || !nom?.trim()) {
      return res.status(400).json({ success: false, message: 'Prénom et nom sont requis.' });
    }

    try {
      // Générer l'ID automatiquement (max + 1)
      const { data: maxData } = await supabase
        .from('team_members')
        .select('id')
        .order('id', { ascending: false })
        .limit(1);

      const newId = (maxData && maxData.length > 0) ? maxData[0].id + 1 : 1;

      // Générer sort_order par défaut si non fourni
      let finalSortOrder = sort_order;
      if (finalSortOrder === undefined || finalSortOrder === null) {
        const { data: maxSort } = await supabase
          .from('team_members')
          .select('sort_order')
          .order('sort_order', { ascending: false })
          .limit(1);
        finalSortOrder = (maxSort && maxSort.length > 0) ? maxSort[0].sort_order + 1 : 1;
      }

      const newMember = {
        id: newId,
        prenom: prenom.trim(),
        nom: nom.trim(),
        role: (role || '').trim(),
        age: age ? parseInt(age, 10) : null,
        profession: (profession || '').trim(),
        photo: (photo || '').trim(),
        depuis: (depuis || '').trim(),
        q1: (q1 || '').trim(),
        q2: (q2 || '').trim(),
        q3: (q3 || '').trim(),
        sort_order: parseInt(finalSortOrder, 10) || 0,
        is_active: is_active !== false,
        updated_at: new Date().toISOString(),
      };

      const { data, error } = await supabase
        .from('team_members')
        .insert(newMember)
        .select()
        .single();

      if (error) throw error;

      return res.status(201).json({ success: true, member: data });
    } catch (err) {
      console.error('[ADMIN TEAM POST ERROR]', err.message);
      return res.status(500).json({ success: false, message: 'Erreur de création.' });
    }
  }

  // ── PATCH : modifier un membre OU réordonner en batch ──────────────────────
  if (req.method === 'PATCH') {
    const body = req.body || {};

    // Cas batch reorder : { reorder: [{ id, sort_order }, ...] }
    if (Array.isArray(body.reorder)) {
      try {
        for (const item of body.reorder) {
          if (!item.id || item.sort_order === undefined) continue;
          const { error } = await supabase
            .from('team_members')
            .update({ sort_order: item.sort_order, updated_at: new Date().toISOString() })
            .eq('id', item.id);
          if (error) throw error;
        }
        return res.status(200).json({ success: true });
      } catch (err) {
        console.error('[ADMIN TEAM REORDER ERROR]', err.message);
        return res.status(500).json({ success: false, message: 'Erreur de réordonnancement.' });
      }
    }

    // Cas modification d'un membre unique
    const { id, ...fields } = body;
    if (!id) {
      return res.status(400).json({ success: false, message: 'ID requis.' });
    }

    try {
      // Construire l'objet de mise à jour (uniquement les champs fournis)
      const updateObj = {};
      if (fields.prenom !== undefined) updateObj.prenom = fields.prenom.trim();
      if (fields.nom !== undefined) updateObj.nom = fields.nom.trim();
      if (fields.role !== undefined) updateObj.role = fields.role.trim();
      if (fields.age !== undefined) updateObj.age = fields.age ? parseInt(fields.age, 10) : null;
      if (fields.profession !== undefined) updateObj.profession = fields.profession.trim();
      if (fields.photo !== undefined) updateObj.photo = fields.photo.trim();
      if (fields.depuis !== undefined) updateObj.depuis = fields.depuis.trim();
      if (fields.q1 !== undefined) updateObj.q1 = fields.q1.trim();
      if (fields.q2 !== undefined) updateObj.q2 = fields.q2.trim();
      if (fields.q3 !== undefined) updateObj.q3 = fields.q3.trim();
      if (fields.sort_order !== undefined) updateObj.sort_order = parseInt(fields.sort_order, 10) || 0;
      if (fields.is_active !== undefined) updateObj.is_active = !!fields.is_active;

      updateObj.updated_at = new Date().toISOString();

      const { error } = await supabase
        .from('team_members')
        .update(updateObj)
        .eq('id', id);

      if (error) throw error;

      return res.status(200).json({ success: true });
    } catch (err) {
      console.error('[ADMIN TEAM PATCH ERROR]', err.message);
      return res.status(500).json({ success: false, message: 'Erreur de mise à jour.' });
    }
  }

  // ── DELETE : toggle is_active (soft delete) ────────────────────────────────
  if (req.method === 'DELETE') {
    const { id, is_active } = req.body || {};

    if (!id) {
      return res.status(400).json({ success: false, message: 'ID requis.' });
    }

    try {
      const { error } = await supabase
        .from('team_members')
        .update({ is_active: !!is_active, updated_at: new Date().toISOString() })
        .eq('id', id);

      if (error) throw error;

      return res.status(200).json({ success: true });
    } catch (err) {
      console.error('[ADMIN TEAM DELETE ERROR]', err.message);
      return res.status(500).json({ success: false, message: 'Erreur de mise à jour.' });
    }
  }

  return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
}
