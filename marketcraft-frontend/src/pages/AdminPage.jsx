import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  LayoutDashboard, Users, Store, Star, Tags,
  ShieldCheck, Trash2, UserCheck, UserX, Package,
  Euro, TrendingUp, Plus, Pencil, Check, X,
} from 'lucide-react';
import { adminAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';
import toast from 'react-hot-toast';

// Sections de la sidebar (remplacent les anciens onglets).
const SECTIONS = [
  { key: 'overview',   label: "Vue d'ensemble", icon: LayoutDashboard },
  { key: 'users',      label: 'Utilisateurs',   icon: Users },
  { key: 'boutiques',  label: 'Boutiques',      icon: Store },
  { key: 'categories', label: 'Catégories',     icon: Tags },
  { key: 'avis',       label: 'Avis',           icon: Star },
];

const ROLE_BADGE = {
  admin:   'bg-red-100 text-red-700',
  vendeur: 'bg-purple-100 text-purple-700',
  client:  'bg-blue-100 text-blue-700',
};

// Accents de bordure haute des cards, façon AdminLTE.
const ACCENT = {
  brown: 'border-t-primary',
  teal:  'border-t-[#17a2b8]',
  green: 'border-t-[#28a745]',
  gold:  'border-t-[#c8860b]',
};

// ── Brique « card » AdminLTE ────────────────────────────────────────────────
function Card({ title, icon: Icon, tools, accent = 'brown', bodyClass = '', children }) {
  return (
    <div className={`bg-white rounded-md border-t-[3px] ${ACCENT[accent]} shadow-[0_1px_3px_rgba(0,0,0,0.1),0_0_1px_rgba(0,0,0,0.12)]`}>
      {title && (
        <div className="flex items-center px-5 py-3 border-b border-secondary-200">
          <h3 className="flex items-center gap-2 font-semibold text-gray-700 text-base">
            {Icon && <Icon size={17} className="text-primary" />} {title}
          </h3>
          {tools && <span className="ml-auto text-xs text-gray-500">{tools}</span>}
        </div>
      )}
      <div className={bodyClass}>{children}</div>
    </div>
  );
}

// ── Widget « small-box » AdminLTE ───────────────────────────────────────────
function SmallBox({ value, label, sub, icon: Icon, color }) {
  return (
    <div className={`relative rounded-lg overflow-hidden text-white shadow ${color}`}>
      <div className="relative z-10 px-4 pt-4 pb-2">
        <p className="text-3xl font-bold leading-none">{value}</p>
        <p className="text-sm opacity-95 mt-1.5">{label}</p>
      </div>
      <Icon size={64} className="absolute top-1.5 right-2 opacity-25 z-0" />
      <div className="relative z-10 bg-black/10 px-4 py-1.5 text-xs">{sub}</div>
    </div>
  );
}

// ── Onglet Vue d'ensemble → small-boxes ─────────────────────────────────────
function OverviewSection() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['admin-stats'],
    queryFn: async () => (await adminAPI.getStats()).data.data,
  });

  if (isLoading) return <p className="text-gray-500 text-sm py-8">Chargement des statistiques…</p>;
  if (isError)   return <p className="text-red-600 text-sm py-8">Impossible de charger les statistiques.</p>;

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
      <SmallBox color="bg-[#17a2b8]" icon={Users} value={data.nb_utilisateurs}
        label="Utilisateurs" sub={`${data.nb_vendeurs} vendeurs · ${data.nb_clients} clients`} />
      <SmallBox color="bg-[#28a745]" icon={Store} value={data.nb_boutiques}
        label="Boutiques" sub={`${data.nb_avis} avis déposés`} />
      <SmallBox color="bg-primary" icon={Package} value={data.nb_produits}
        label="Produits actifs" sub={`${data.nb_commandes} commandes`} />
      <SmallBox color="bg-[#c8860b]" icon={Euro} value={`${Number(data.ca_global).toFixed(2)} €`}
        label="CA global" sub={<span className="inline-flex items-center gap-1"><TrendingUp size={12} /> hors annulées</span>} />
    </div>
  );
}

// ── Table réutilisable (en-tête AdminLTE) ───────────────────────────────────
function Th({ children, right }) {
  return <th className={`px-5 py-3 font-semibold text-gray-600 text-sm ${right ? 'text-right' : 'text-left'}`}>{children}</th>;
}

// ── Onglet Utilisateurs ─────────────────────────────────────────────────────
function UsersSection() {
  const queryClient = useQueryClient();
  const { data: users = [], isLoading } = useQuery({
    queryKey: ['admin-users'],
    queryFn: async () => (await adminAPI.getUsers()).data.data,
  });

  const { mutate: toggle } = useMutation({
    mutationFn: (id) => adminAPI.toggleUser(id),
    onSuccess: () => { queryClient.invalidateQueries(['admin-users']); toast.success('Statut du compte mis à jour.'); },
    onError: (e) => toast.error(e.response?.data?.error || 'Action impossible.'),
  });

  return (
    <Card title="Utilisateurs" icon={Users} tools={`${users.length} comptes`} accent="teal" bodyClass="overflow-x-auto">
      {isLoading ? (
        <p className="text-gray-500 text-sm p-6">Chargement…</p>
      ) : (
        <table className="w-full text-sm">
          <thead className="bg-secondary-50 border-b-2 border-secondary-200">
            <tr><Th>Nom</Th><Th>Email</Th><Th>Rôle</Th><Th>Statut</Th><Th right>Action</Th></tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id} className="border-t border-secondary-100 odd:bg-white even:bg-secondary-50/50 hover:bg-secondary-100/60 transition-colors">
                <td className="px-5 py-3 font-medium text-gray-800">{u.prenom} {u.nom}</td>
                <td className="px-5 py-3 text-gray-500">{u.email}</td>
                <td className="px-5 py-3">
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${ROLE_BADGE[u.role] || 'bg-gray-100 text-gray-700'}`}>{u.role}</span>
                </td>
                <td className="px-5 py-3">
                  {Number(u.est_actif) === 1
                    ? <span className="text-green-600 text-xs font-medium">Actif</span>
                    : <span className="text-red-500 text-xs font-medium">Désactivé</span>}
                </td>
                <td className="px-5 py-3 text-right">
                  <button
                    onClick={() => toggle(u.id)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium border transition-colors ${
                      Number(u.est_actif) === 1
                        ? 'text-red-600 border-red-200 hover:bg-red-50'
                        : 'text-green-600 border-green-200 hover:bg-green-50'
                    }`}
                  >
                    {Number(u.est_actif) === 1 ? <><UserX size={14} /> Désactiver</> : <><UserCheck size={14} /> Activer</>}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Card>
  );
}

// ── Onglet Boutiques ──────────────────────────────────────────────────────────
function BoutiquesSection() {
  const queryClient = useQueryClient();
  const { data: boutiques = [], isLoading } = useQuery({
    queryKey: ['admin-boutiques'],
    queryFn: async () => (await adminAPI.getBoutiques()).data.data,
  });

  const { mutate: toggle } = useMutation({
    mutationFn: (id) => adminAPI.toggleBoutique(id),
    onSuccess: () => { queryClient.invalidateQueries(['admin-boutiques']); toast.success('Statut de la boutique mis à jour.'); },
    onError: (e) => toast.error(e.response?.data?.error || 'Action impossible.'),
  });

  return (
    <Card title="Boutiques" icon={Store} tools={`${boutiques.length} boutiques`} accent="green" bodyClass="overflow-x-auto">
      {isLoading ? (
        <p className="text-gray-500 text-sm p-6">Chargement…</p>
      ) : (
        <table className="w-full text-sm">
          <thead className="bg-secondary-50 border-b-2 border-secondary-200">
            <tr><Th>Boutique</Th><Th>Vendeur</Th><Th>Produits</Th><Th>Note</Th><Th>Statut</Th><Th right>Action</Th></tr>
          </thead>
          <tbody>
            {boutiques.map((b) => (
              <tr key={b.id} className="border-t border-secondary-100 odd:bg-white even:bg-secondary-50/50 hover:bg-secondary-100/60 transition-colors">
                <td className="px-5 py-3 font-medium text-gray-800">{b.nom}</td>
                <td className="px-5 py-3 text-gray-500">{b.vendeur_prenom} {b.vendeur_nom}</td>
                <td className="px-5 py-3 text-gray-600">{b.nb_produits}</td>
                <td className="px-5 py-3 text-gray-600"><span className="text-amber-500">★</span> {Number(b.note_moyenne).toFixed(1)}</td>
                <td className="px-5 py-3">
                  {Number(b.est_active) === 1
                    ? <span className="text-green-600 text-xs font-medium">Active</span>
                    : <span className="text-red-500 text-xs font-medium">Suspendue</span>}
                </td>
                <td className="px-5 py-3 text-right">
                  <button
                    onClick={() => toggle(b.id)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium border transition-colors ${
                      Number(b.est_active) === 1
                        ? 'text-red-600 border-red-200 hover:bg-red-50'
                        : 'text-green-600 border-green-200 hover:bg-green-50'
                    }`}
                  >
                    {Number(b.est_active) === 1 ? 'Suspendre' : 'Réactiver'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Card>
  );
}

// ── Onglet Avis ─────────────────────────────────────────────────────────────────
function AvisSection() {
  const queryClient = useQueryClient();
  const { data: avis = [], isLoading } = useQuery({
    queryKey: ['admin-avis'],
    queryFn: async () => (await adminAPI.getAvis()).data.data,
  });

  const { mutate: remove } = useMutation({
    mutationFn: (id) => adminAPI.deleteAvis(id),
    onSuccess: () => { queryClient.invalidateQueries(['admin-avis']); toast.success('Avis supprimé.'); },
    onError: (e) => toast.error(e.response?.data?.error || 'Suppression impossible.'),
  });

  return (
    <Card title="Modération des avis" icon={Star} tools={`${avis.length} avis`} accent="gold" bodyClass="p-4">
      {isLoading ? (
        <p className="text-gray-500 text-sm py-4">Chargement…</p>
      ) : avis.length === 0 ? (
        <p className="text-gray-500 text-sm py-4">Aucun avis à modérer.</p>
      ) : (
        <div className="space-y-3">
          {avis.map((a) => (
            <div key={a.id} className="flex items-start justify-between gap-4 rounded-lg border border-secondary-200 p-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-amber-500 text-sm">{'★'.repeat(a.note)}{'☆'.repeat(5 - a.note)}</span>
                  <span className="text-xs text-gray-500">
                    {a.auteur_prenom} {a.auteur_nom} · sur <span className="font-medium">{a.produit_nom}</span>
                  </span>
                </div>
                {a.titre && <p className="text-sm font-semibold text-gray-800">{a.titre}</p>}
                {a.commentaire && <p className="text-sm text-gray-600 line-clamp-3">{a.commentaire}</p>}
              </div>
              <button
                onClick={() => { if (window.confirm('Supprimer cet avis ?')) remove(a.id); }}
                className="flex items-center gap-1.5 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-md text-xs font-medium border border-red-200 transition-colors flex-shrink-0"
              >
                <Trash2 size={14} /> Supprimer
              </button>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}

// ── Onglet Catégories ───────────────────────────────────────────────────────────
function CategoriesSection() {
  const queryClient = useQueryClient();
  const [nom, setNom] = useState('');
  const [description, setDescription] = useState('');
  const [parentId, setParentId] = useState('');
  const [editId, setEditId] = useState(null);
  const [editNom, setEditNom] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['admin-categories'],
    queryFn: async () => (await adminAPI.getCategories()).data.data,
  });

  const categories = data?.categories ?? [];
  const racines = data?.racines ?? [];

  const refresh = () => {
    queryClient.invalidateQueries(['admin-categories']);
    queryClient.invalidateQueries(['categories']);
  };

  const { mutate: create, isPending: creating } = useMutation({
    mutationFn: () => adminAPI.createCategorie({
      nom: nom.trim(),
      description: description.trim() || null,
      parent_id: parentId || null,
    }),
    onSuccess: () => { setNom(''); setDescription(''); refresh(); toast.success('Catégorie créée.'); },
    onError: (e) => toast.error(e.response?.data?.error || 'Création impossible.'),
  });

  const { mutate: rename } = useMutation({
    mutationFn: ({ id, value }) => adminAPI.updateCategorie(id, { nom: value }),
    onSuccess: () => { setEditId(null); refresh(); toast.success('Catégorie renommée.'); },
    onError: (e) => toast.error(e.response?.data?.error || 'Modification impossible.'),
  });

  const { mutate: remove } = useMutation({
    mutationFn: async (cat) => {
      try {
        return await adminAPI.deleteCategorie(cat.id);
      } catch (err) {
        if (err.response?.status !== 409) throw err;
        const { principale = 0, liaisons = 0 } = err.response.data?.details || {};
        const ok = window.confirm(
          `« ${cat.nom} » est utilisée par ${principale} produit(s) en catégorie principale ` +
          `et ${liaisons} association(s) secondaire(s).\n\n` +
          `Les produits ne seront pas supprimés, mais ils perdront cette catégorie. Continuer ?`
        );
        if (!ok) return null;
        return adminAPI.deleteCategorie(cat.id, true);
      }
    },
    onSuccess: (res) => { if (res === null) return; refresh(); toast.success('Catégorie supprimée.'); },
    onError: (e) => toast.error(e.response?.data?.error || 'Suppression impossible.'),
  });

  const submit = (e) => {
    e.preventDefault();
    if (nom.trim().length < 2) { toast.error('Le nom doit contenir au moins 2 caractères.'); return; }
    create();
  };

  return (
    <div className="space-y-5">
      {/* Formulaire de création */}
      <Card title="Nouvelle catégorie" icon={Plus} accent="brown" bodyClass="p-4">
        <form onSubmit={submit} className="flex flex-col sm:flex-row gap-2">
          <input type="text" value={nom} onChange={(e) => setNom(e.target.value)}
            placeholder="Nom (ex. Céramique)" maxLength={100} className="input-field flex-1" />
          <input type="text" value={description} onChange={(e) => setDescription(e.target.value)}
            placeholder="Description (facultatif)" maxLength={1000} className="input-field flex-1" />
          <select value={parentId} onChange={(e) => setParentId(e.target.value)} className="input-field sm:w-44">
            <option value="">Racine</option>
            {racines.map((r) => <option key={r.id} value={r.id}>{r.nom}</option>)}
          </select>
          <button type="submit" disabled={creating} className="btn-primary flex items-center gap-1.5 justify-center">
            <Plus size={16} /> {creating ? 'Ajout…' : 'Ajouter'}
          </button>
        </form>
      </Card>

      {/* Liste */}
      <Card title="Catégories" icon={Tags} tools={`${categories.length} catégories`} accent="brown" bodyClass="overflow-x-auto">
        {isLoading ? (
          <p className="text-gray-500 text-sm p-6">Chargement…</p>
        ) : categories.length === 0 ? (
          <p className="text-gray-500 text-sm p-6">Aucune catégorie pour le moment.</p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-secondary-50 border-b-2 border-secondary-200">
              <tr><Th>Nom</Th><Th>Groupe</Th><Th>Slug</Th><Th>Produits</Th><Th right>Actions</Th></tr>
            </thead>
            <tbody>
              {categories.map((c) => (
                <tr key={c.id} className="border-t border-secondary-100 odd:bg-white even:bg-secondary-50/50 hover:bg-secondary-100/60">
                  <td className="px-5 py-3">
                    {editId === c.id ? (
                      <input type="text" value={editNom} autoFocus onChange={(e) => setEditNom(e.target.value)}
                        onKeyDown={(e) => { if (e.key === 'Enter') rename({ id: c.id, value: editNom.trim() }); if (e.key === 'Escape') setEditId(null); }}
                        className="input-field py-1 text-sm" />
                    ) : (
                      <span className="font-medium text-gray-800">{c.nom}</span>
                    )}
                  </td>
                  <td className="px-5 py-3">
                    {c.parent_nom
                      ? <span className="text-xs bg-secondary-100 text-gray-600 px-2 py-0.5 rounded-full">{c.parent_nom}</span>
                      : <span className="text-xs text-gray-400">racine</span>}
                  </td>
                  <td className="px-5 py-3 text-gray-500 font-mono text-xs">{c.slug}</td>
                  <td className="px-5 py-3">
                    <span className="inline-flex items-center gap-1 text-gray-600"><Package size={14} /> {c.nb_produits}</span>
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex items-center justify-end gap-1">
                      {editId === c.id ? (
                        <>
                          <button onClick={() => rename({ id: c.id, value: editNom.trim() })} disabled={editNom.trim().length < 2}
                            className="p-1.5 rounded-md text-green-600 hover:bg-green-50 disabled:opacity-40" title="Valider"><Check size={15} /></button>
                          <button onClick={() => setEditId(null)} className="p-1.5 rounded-md text-gray-500 hover:bg-secondary-100" title="Annuler"><X size={15} /></button>
                        </>
                      ) : (
                        <>
                          <button onClick={() => { setEditId(c.id); setEditNom(c.nom); }} className="p-1.5 rounded-md text-gray-500 hover:bg-secondary-100" title="Renommer"><Pencil size={15} /></button>
                          <button onClick={() => remove(c)} className="p-1.5 rounded-md text-red-600 hover:bg-red-50" title="Supprimer"><Trash2 size={15} /></button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}

// ── Page principale — layout AdminLTE (sidebar + contenu) ────────────────────
export default function AdminPage() {
  const [active, setActive] = useState('overview');
  const { user } = useAuth();

  const initials = ((user?.prenom || '')[0] || '') + ((user?.nom || '')[0] || '') || 'A';
  const current = SECTIONS.find((s) => s.key === active);

  return (
    <div className="flex min-h-[calc(100vh-4rem)] bg-[#f4f1ea]">
      {/* Sidebar sombre à accent brun */}
      <aside className="w-60 flex-shrink-0 bg-[#2a211c] text-[#c7bdb2] hidden md:block">
        <div className="flex items-center gap-2.5 px-4 py-4 border-b border-white/10">
          <div className="w-9 h-9 rounded-lg bg-primary flex items-center justify-center flex-shrink-0">
            <ShieldCheck size={18} className="text-white" />
          </div>
          <span className="text-lg font-semibold text-[#f5efe8]">Back-office</span>
        </div>

        <div className="flex items-center gap-3 px-4 py-4 mx-2 border-b border-white/10">
          <div className="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold text-sm">
            {initials.toUpperCase()}
          </div>
          <div className="min-w-0">
            <p className="text-sm font-medium text-[#f5efe8] truncate">{user?.prenom} {user?.nom}</p>
            <p className="text-xs text-[#a8998c]">Administrateur</p>
          </div>
        </div>

        <nav className="p-2">
          <p className="px-3 pt-3 pb-1.5 text-[11px] uppercase tracking-wider text-[#7c6e62]">Supervision</p>
          {SECTIONS.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              onClick={() => setActive(key)}
              className={`w-full flex items-center gap-3 px-3 py-2.5 my-0.5 rounded-md text-sm transition-colors ${
                active === key
                  ? 'bg-primary text-white shadow'
                  : 'text-[#c7bdb2] hover:bg-white/5'
              }`}
            >
              <Icon size={18} /> {label}
            </button>
          ))}
        </nav>
      </aside>

      {/* Contenu */}
      <div className="flex-1 min-w-0">
        {/* En-tête de contenu + fil d'Ariane */}
        <div className="flex items-center justify-between px-6 pt-6 pb-4">
          <div>
            <h1 className="text-2xl font-serif font-bold text-gray-700">Administration</h1>
            <p className="text-gray-500 text-sm">Supervision de la plateforme MarketCraft</p>
          </div>
          <div className="text-sm text-gray-400 hidden sm:block">
            Accueil / <span className="text-primary font-medium">{current?.label}</span>
          </div>
        </div>

        {/* Onglets horizontaux (repli mobile, la sidebar étant masquée) */}
        <div className="md:hidden flex gap-1 px-6 mb-3 overflow-x-auto">
          {SECTIONS.map(({ key, label, icon: Icon }) => (
            <button key={key} onClick={() => setActive(key)}
              className={`flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium whitespace-nowrap ${
                active === key ? 'bg-primary text-white' : 'text-gray-600 bg-white border border-secondary-200'
              }`}>
              <Icon size={15} /> {label}
            </button>
          ))}
        </div>

        <div className="px-6 pb-8">
          {active === 'overview'   && <OverviewSection />}
          {active === 'users'      && <UsersSection />}
          {active === 'boutiques'  && <BoutiquesSection />}
          {active === 'categories' && <CategoriesSection />}
          {active === 'avis'       && <AvisSection />}
        </div>
      </div>
    </div>
  );
}
