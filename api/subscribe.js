/**
 * api/subscribe.js
 * Vercel Serverless Function — Inscription OPTIN
 *  - Validation des données
 *  - Ajout contact dans Brevo (liste)
 *  - Envoi d'un email de confirmation via Brevo API
 */

export default async function handler(req, res) {
  // CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Méthode non autorisée.' });
  }

  const { prenom, nom, email, telephone } = req.body || {};

  // ── Validation ──────────────────────────────────────────────────────────────
  const errors = [];
  if (!prenom?.trim())                                      errors.push('Le prénom est requis.');
  if (!nom?.trim())                                         errors.push('Le nom est requis.');
  if (!email?.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Un email valide est requis.');
  if (!telephone?.trim())                                   errors.push('Le numéro de portable est requis.');

  if (errors.length) {
    return res.status(400).json({ success: false, message: errors.join(' ') });
  }

  const clean = {
    prenom: escapeHtml(prenom.trim()),
    nom: escapeHtml(nom.trim()),
    email: email.trim(),
    telephone: telephone.trim().replace(/[^0-9+\s\-()]/g, ''),
  };

  const BREVO_API_KEY = process.env.BREVO_API_KEY || '';
  const BREVO_LIST_ID = parseInt(process.env.BREVO_LIST_ID || '2', 10);
  const FROM_EMAIL = 'contact@entreprendre-chateauneuf.fr';
  const FROM_NAME = 'Entreprendre Ensemble pour Châteauneuf';

  // ── Ajout dans Brevo (liste de contacts) ──────────────────────────────────
  await addContactToBrevo(clean, BREVO_API_KEY, BREVO_LIST_ID);

  // ── Email de confirmation ─────────────────────────────────────────────────
  await sendViaBrevo({
    apiKey: BREVO_API_KEY,
    to: [{ email: clean.email, name: `${clean.prenom} ${clean.nom}` }],
    sender: { name: FROM_NAME, email: FROM_EMAIL },
    subject: 'Votre inscription — Entreprendre Ensemble pour Châteauneuf',
    htmlContent: buildConfirmationEmail(clean, FROM_EMAIL),
  });

  return res.status(200).json({
    success: true,
    message: 'Inscription réussie ! Vous allez recevoir un email de confirmation.',
  });
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

async function addContactToBrevo(clean, apiKey, listId) {
  if (!apiKey) return false;
  try {
    const resp = await fetch('https://api.brevo.com/v3/contacts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'api-key': apiKey,
      },
      body: JSON.stringify({
        email: clean.email,
        attributes: {
          PRENOM: clean.prenom,
          NOM: clean.nom,
          SMS: clean.telephone,
        },
        listIds: [listId],
        updateEnabled: true,
      }),
    });
    return resp.ok;
  } catch {
    return false;
  }
}

async function sendViaBrevo({ apiKey, to, sender, subject, htmlContent }) {
  if (!apiKey) return false;
  try {
    const resp = await fetch('https://api.brevo.com/v3/smtp/email', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'api-key': apiKey,
      },
      body: JSON.stringify({ sender, to, subject, htmlContent }),
    });
    return resp.ok;
  } catch {
    return false;
  }
}

function buildConfirmationEmail({ prenom }, fromEmail) {
  return `<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Confirmation d'inscription</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0098D8,#007AAD);padding:36px 40px;text-align:center;">
            <h1 style="color:#fff;font-size:22px;margin:0 0 8px;">Entreprendre Ensemble</h1>
            <p style="color:rgba(255,255,255,.85);margin:0;font-size:14px;">pour Châteauneuf</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px;">
            <h2 style="color:#1f2937;font-size:20px;margin:0 0 16px;">Bonjour ${prenom} !</h2>
            <p style="color:#374151;line-height:1.7;margin:0 0 16px;">
              Merci pour votre inscription. Vous recevrez très prochainement notre programme complet pour Châteauneuf.
            </p>
            <div style="background:#E6F7FC;border-left:4px solid #0098D8;border-radius:0 8px 8px 0;padding:16px 20px;margin:24px 0;">
              <p style="margin:0;color:#005B82;font-weight:600;font-size:15px;">📅 Réunion publique — Mardi 11 mars 2026</p>
              <p style="margin:6px 0 0;color:#005B82;font-size:14px;">19h00 · Salle communale de Châteauneuf</p>
              <p style="margin:6px 0 0;color:#374151;font-size:14px;">Venez rencontrer l'équipe et découvrir le programme en détail !</p>
            </div>
            <p style="color:#374151;line-height:1.7;margin:0 0 24px;">
              En attendant, n'hésitez pas à suivre notre actualité et à partager cette initiative autour de vous.
            </p>
            <p style="color:#374151;line-height:1.7;margin:0;">
              À très bientôt,<br>
              <strong>L'équipe Entreprendre Ensemble pour Châteauneuf</strong>
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb;padding:24px 40px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af;font-size:12px;margin:0;">
              Vous recevez cet email car vous vous êtes inscrit sur notre site.<br>
              Conformément au RGPD, vous pouvez vous désinscrire à tout moment en nous contactant à
              <a href="mailto:${fromEmail}" style="color:#0098D8;">${fromEmail}</a>.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>`;
}
