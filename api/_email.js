/**
 * api/_email.js
 * Module partagé pour l'envoi d'emails via SMTP (nodemailer)
 * Ce fichier n'est PAS un endpoint (préfixé par _)
 */

import nodemailer from 'nodemailer';

let _transporter = null;

function getTransporter() {
  if (_transporter) return _transporter;

  _transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST || 'smtp-relay.brevo.com',
    port: parseInt(process.env.SMTP_PORT || '587', 10),
    secure: false,
    auth: {
      user: process.env.SMTP_USER || '',
      pass: process.env.SMTP_PASS || '',
    },
  });

  return _transporter;
}

export function getFromEmail() {
  return process.env.SMTP_FROM_EMAIL || 'contact@entreprendre-chateauneuf.fr';
}

export function getFromName() {
  return process.env.SMTP_FROM_NAME || 'Entreprendre Ensemble pour Châteauneuf';
}

export function getTeamEmails() {
  const raw = process.env.EMAIL_TO || 'contact@entreprendre-chateauneuf.fr';
  return raw.split(',').map(e => e.trim()).filter(Boolean);
}

/**
 * Envoie un email via SMTP
 */
export async function sendEmail({ to, subject, html }) {
  const transporter = getTransporter();

  try {
    await transporter.sendMail({
      from: `"${getFromName()}" <${getFromEmail()}>`,
      to: Array.isArray(to) ? to.join(', ') : to,
      subject,
      html,
    });
    return true;
  } catch (err) {
    console.error('[EMAIL ERROR]', err.message);
    return false;
  }
}

/**
 * Ajoute un contact dans Brevo via l'API (optionnel, nécessite BREVO_API_KEY)
 */
export async function addContactToBrevo({ email, prenom, nom, telephone }) {
  const apiKey = process.env.BREVO_API_KEY;
  if (!apiKey) return false;

  const listId = parseInt(process.env.BREVO_LIST_ID || '2', 10);

  try {
    const resp = await fetch('https://api.brevo.com/v3/contacts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'api-key': apiKey,
      },
      body: JSON.stringify({
        email,
        attributes: { PRENOM: prenom, NOM: nom, SMS: telephone },
        listIds: [listId],
        updateEnabled: true,
      }),
    });
    return resp.ok;
  } catch {
    return false;
  }
}

export function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
