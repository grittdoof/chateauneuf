/**
 * api/_supabase.js
 * Module partagé — Client Supabase (service_role)
 * Ce fichier n'est PAS un endpoint (préfixé par _)
 */

import { createClient } from '@supabase/supabase-js';

let _supabase = null;

export function getSupabase() {
  if (_supabase) return _supabase;

  const url = process.env.SUPABASE_URL;
  const serviceKey = process.env.SUPABASE_SERVICE_KEY;

  if (!url || !serviceKey) {
    throw new Error('SUPABASE_URL et SUPABASE_SERVICE_KEY requis dans les variables d\'environnement.');
  }

  _supabase = createClient(url, serviceKey, {
    auth: { persistSession: false },
  });

  return _supabase;
}
