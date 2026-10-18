import { admin } from '@/api/endpoints'

/**
 * بحث الحقول المرتبطة بسجلات أخرى (لـ SearchSelect).
 *
 * عشرون نتيجة تكفي من يكتب ما يبحث عنه، والبحث في الخادم يصل إلى كل
 * السجلات لا إلى أول مئتين منها.
 */
const LIMIT = 20

export async function searchFamilies(q) {
  const { data } = await admin.families.list({ q, per_page: LIMIT })
  return (data.data ?? []).map((f) => ({
    value: f.id,
    label: `${f.family_code} · ${f.guardian?.full_name ?? 'بلا وصيّ'}`,
    hint: [f.country?.name, f.city?.name].filter(Boolean).join('، '),
  }))
}

export async function searchOrphans(q, extra = {}) {
  const { data } = await admin.orphans.list({ q, per_page: LIMIT, ...extra })
  return (data.data ?? []).map((o) => ({
    value: o.id,
    label: `${o.first_name} · ${o.reference_code}`,
    hint: o.status_label,
  }))
}

export async function searchLiveSponsorships(q) {
  const { data } = await admin.sponsorships.list({ q, live: 1, per_page: LIMIT })
  return (data.data ?? []).map((s) => ({
    value: s.id,
    label: `${s.reference_code} · ${s.sponsor?.name ?? s.sponsor?.sponsor_code ?? 'كافل'} ← ${s.orphan?.name ?? ''}`,
    hint: s.status === 'pending' ? 'بانتظار أول دفعة' : s.status === 'paused' ? 'موقوفة' : s.status_label,
    sponsorship: s,
  }))
}

export const orphanLabel = (o) => (o ? `${o.first_name ?? o.name} · ${o.reference_code}` : '')
export const familyLabel = (f) => (f ? `${f.family_code} · ${f.guardian?.full_name ?? 'بلا وصيّ'}` : '')
