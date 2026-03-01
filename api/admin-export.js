/**
 * api/admin-export.js
 * Vercel Serverless Function — Export CSV / Excel des données
 * Usage : /api/admin-export?type=subscriptions&format=csv&token=xxx
 */

import { verifyToken } from './admin-login.js';
import { getSupabase } from './_supabase.js';

export default async function handler(req, res) {
  if (req.method !== 'GET') {
    return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
  }

  // Auth via header OU query param (pour les liens de téléchargement)
  const authHeader = req.headers.authorization || '';
  const headerToken = authHeader.replace('Bearer ', '');
  const queryToken = req.query.token || '';
  const token = headerToken || queryToken;

  if (!verifyToken(token)) {
    return res.status(401).json({ success: false, message: 'Non autorisé.' });
  }

  const type = req.query.type || 'subscriptions';
  const format = req.query.format || 'csv';
  const supabase = getSupabase();

  try {
    let rows, headers, filename;

    if (type === 'ideas') {
      const { data, error } = await supabase
        .from('ideas')
        .select('*')
        .order('created_at', { ascending: false });
      if (error) throw error;

      rows = data || [];
      headers = ['Prénom', 'Nom', 'Email', 'Téléphone', 'Sujet', 'Message', 'Lu', 'Date de soumission'];
      filename = 'idees_citoyennes_chateauneuf_';
    } else {
      const { data, error } = await supabase
        .from('subscriptions')
        .select('*')
        .order('created_at', { ascending: false });
      if (error) throw error;

      rows = data || [];
      headers = ['Prénom', 'Nom', 'Email', 'Téléphone', 'Date d\'inscription'];
      filename = 'inscriptions_chateauneuf_';
    }

    const separator = format === 'excel' ? ';' : ',';
    const BOM = format === 'excel' ? '\uFEFF' : '';
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');

    let csv = BOM + headers.join(separator) + '\n';

    for (const row of rows) {
      const dateFormatted = row.created_at
        ? new Date(row.created_at).toLocaleString('fr-FR', { timeZone: 'Europe/Paris' })
        : '';

      let values;
      if (type === 'ideas') {
        values = [
          csvEscape(row.prenom, separator),
          csvEscape(row.nom, separator),
          csvEscape(row.email, separator),
          csvEscape(row.telephone, separator),
          csvEscape(row.sujet, separator),
          csvEscape(row.message, separator),
          row.is_read ? 'Oui' : 'Non',
          dateFormatted,
        ];
      } else {
        values = [
          csvEscape(row.prenom, separator),
          csvEscape(row.nom, separator),
          csvEscape(row.email, separator),
          csvEscape(row.telephone, separator),
          dateFormatted,
        ];
      }

      csv += values.join(separator) + '\n';
    }

    const mimeType = format === 'excel'
      ? 'application/vnd.ms-excel; charset=utf-8'
      : 'text/csv; charset=utf-8';

    res.setHeader('Content-Type', mimeType);
    res.setHeader('Content-Disposition', `attachment; filename="${filename}${dateStr}.csv"`);
    return res.send(csv);

  } catch (err) {
    console.error('[EXPORT ERROR]', err.message);
    return res.status(500).json({ success: false, message: 'Erreur lors de l\'export.' });
  }
}

function csvEscape(value, separator) {
  if (!value) return '';
  const str = String(value);
  if (str.includes(separator) || str.includes('"') || str.includes('\n') || str.includes('\r')) {
    return '"' + str.replace(/"/g, '""') + '"';
  }
  return str;
}
