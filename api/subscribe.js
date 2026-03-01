/**
 * api/subscribe.js
 * Vercel Serverless Function — Inscription OPTIN
 */

import { sendEmail, addContactToBrevo, escapeHtml, getFromEmail, getFromName, getTeamEmails } from './_email.js';
import { getSupabase } from './_supabase.js';

export default async function handler(req, res) {
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
  if (!prenom?.trim())  errors.push('Le prénom est requis.');
  if (!nom?.trim())     errors.push('Le nom est requis.');
  if (!email?.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
    errors.push('Un email valide est requis.');
  if (!telephone?.trim()) errors.push('Le numéro de portable est requis.');

  if (errors.length) {
    return res.status(400).json({ success: false, message: errors.join(' ') });
  }

  const clean = {
    prenom: escapeHtml(prenom.trim()),
    nom: escapeHtml(nom.trim()),
    email: email.trim(),
    telephone: telephone.trim().replace(/[^0-9+\s\-()]/g, ''),
  };

  const fromEmail = getFromEmail();
  const fromName = getFromName();

  // ── Enregistrement dans Supabase ────────────────────────────────────────
  try {
    const supabase = getSupabase();
    const { error: dbError } = await supabase
      .from('subscriptions')
      .upsert({
        prenom: clean.prenom,
        nom: clean.nom,
        email: clean.email,
        telephone: clean.telephone,
      }, { onConflict: 'email' });

    if (dbError) console.error('[SUPABASE ERROR]', dbError.message);
  } catch (err) {
    console.error('[SUPABASE ERROR]', err.message);
  }

  // ── Ajout dans Brevo (si API key configurée — optionnel) ──────────────────
  await addContactToBrevo(clean);

  // ── Email de confirmation à l'utilisateur ─────────────────────────────────
  await sendEmail({
    to: clean.email,
    subject: 'Votre inscription — Entreprendre Ensemble pour Châteauneuf',
    html: buildConfirmationEmail(clean, fromEmail),
  });

  // ── Notification à l'équipe ───────────────────────────────────────────────
  await sendEmail({
    to: getTeamEmails(),
    subject: `Nouvelle inscription : ${clean.prenom} ${clean.nom}`,
    html: buildTeamNotifEmail(clean),
  });

  return res.status(200).json({
    success: true,
    message: 'Inscription réussie ! Vous allez recevoir un email de confirmation.',
  });
}

// ═════════════════════════════════════════════════════════════════════════════
// TEMPLATES EMAIL
// ═════════════════════════════════════════════════════════════════════════════

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
              <p style="margin:0;color:#005B82;font-weight:600;font-size:15px;">Réunion publique — Mardi 11 mars 2026</p>
              <p style="margin:6px 0 0;color:#005B82;font-size:14px;">19h00 · Salle communale de Châteauneuf</p>
              <p style="margin:6px 0 0;color:#374151;font-size:14px;">Venez rencontrer l'équipe et découvrir le programme en détail !</p>
            </div>
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
              <a href="mailto:${fromEmail}" style="color:#0098D8;">${fromEmail}</a>
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>`;
}

function buildTeamNotifEmail({ prenom, nom, email, telephone }) {
  return `<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Nouvelle inscription</title></head>
<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0098D8,#007AAD);padding:28px 40px;text-align:center;">
            <h1 style="color:#fff;font-size:20px;margin:0;">Nouvelle inscription</h1>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr><td style="color:#6b7280;font-size:13px;padding:6px 0;width:100px;"><strong>Nom :</strong></td><td style="color:#1f2937;font-size:14px;">${prenom} ${nom}</td></tr>
              <tr><td style="color:#6b7280;font-size:13px;padding:6px 0;"><strong>Email :</strong></td><td style="color:#1f2937;font-size:14px;"><a href="mailto:${email}" style="color:#0098D8;">${email}</a></td></tr>
              <tr><td style="color:#6b7280;font-size:13px;padding:6px 0;"><strong>Tél :</strong></td><td style="color:#1f2937;font-size:14px;">${telephone}</td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af;font-size:12px;margin:0;">Inscription depuis le site entreprendre-chateauneuf.fr</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>`;
}
