/**
 * api/admin-contacts.js
 * Vercel Serverless Function — Récupérer les contacts Brevo pour l'admin
 */

import { verifyToken } from './admin-login.js';

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

  const apiKey = process.env.BREVO_API_KEY;
  if (!apiKey) {
    return res.status(200).json({
      success: true,
      contacts: [],
      message: 'BREVO_API_KEY non configurée. Les contacts sont gérés directement dans Brevo.',
    });
  }

  const listId = parseInt(process.env.BREVO_LIST_ID || '2', 10);

  try {
    // Récupérer les contacts de la liste
    const resp = await fetch(
      `https://api.brevo.com/v3/contacts/lists/${listId}/contacts?limit=500&offset=0`,
      {
        headers: {
          'Accept': 'application/json',
          'api-key': apiKey,
        },
      }
    );

    if (!resp.ok) {
      return res.status(200).json({
        success: true,
        contacts: [],
        message: `Erreur Brevo (${resp.status}). Vérifiez votre BREVO_API_KEY et BREVO_LIST_ID.`,
      });
    }

    const data = await resp.json();
    const contacts = (data.contacts || []).map(c => ({
      email: c.email,
      prenom: c.attributes?.PRENOM || '',
      nom: c.attributes?.NOM || '',
      telephone: c.attributes?.SMS || '',
      date: c.createdAt || '',
    }));

    // Trier par date (plus récents en premier)
    contacts.sort((a, b) => new Date(b.date) - new Date(a.date));

    return res.status(200).json({
      success: true,
      contacts,
      total: data.count || contacts.length,
    });
  } catch (err) {
    return res.status(500).json({
      success: false,
      message: 'Erreur lors de la récupération des contacts.',
    });
  }
}
