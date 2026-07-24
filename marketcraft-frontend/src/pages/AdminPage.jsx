import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  BarChart3, Users, Store, Star, ShieldCheck, Trash2,
  UserCheck, UserX, Package, ShoppingBag,
  Tags, Plus, Pencil, Check, X,
} from 'lucide-react';
import { adminAPI } from '../services/api';
import toast from 'react-hot-toast';

const TABS = [
  { key: 'overview',   label: "Vue d'ensemble", icon: BarChart3 },
  { key: 'users',      label: 'Utilisateurs',   icon: Users     },
  { key: 'boutiques',  label: 'Boutiques',      icon: Store     },
  { key: 'categories', label: 'Catégories',     icon: Tags      },
  { key: 'avis',       label: 'Avis',           icon: Star      },
];

const ROLE_BADGE = {
  admin:   'bg-red-100 text-red-700',
  vendeur: 'bg-purple-100 text-purple-700',
  client:  'bg-blue-100 text-blue-700',
};

function StatCard({ icon: Icon, label, value, suffix }) {
  return (
    <div className="card p-5 flex items-center gap-4">
      <div className="w-11 h-11 rounded-xl bg-primary-100 flex items-center justify-center flex-shrink-0">
        <Icon size={20} className="text-primary" />
      </div>
      <div>
        <p className="text-2xl font-bold text-gray-800">{value}{suffix}</p>
        <p className="text-xs text-gray-500">{label}</p>
      </div>
    </div>
  );
}

// ── Onglet Vue d'ensemble ──────────────────────────────────────────────────────
function OverviewTab() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['admin-stats'],
    queryFn: async () => (await adminAPI.getStats()).data.data,
  });

  if (isLoading) return <p className="text-gray-500 text-sm py-8">Chargement des statistiques…</p>;
  if (isError)   return <p className="text-red-600 text-sm py-8">Impossible de charger les statistiques.</p>;

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCard icon={Users}       label="Utilisateurs"      value={data.nb_utilisateurs} />
      <StatCard icon={Store}       label="Vendeurs"          value={data.nb_vendeurs} />
      <StatCard icon={UserCheck}   label="Clients"           value={data.nb_clients} />
      <StatCard icon={Store}       label="Boutiques"         value={data.nb_boutiques} />
      <StatCard icon={Package}     label="Produits actifs"   value={data.nb_produits} />
      <StatCard icon={ShoppingBag} label="Commandes"         value={data.nb_commandes} />
      <StatCard icon={Star}        label="Avis"              value={data.nb_avis} />
      <StatCard icon={BarChart3}   label="CA global"         value={Number(data.ca_global).toFixed(2)} suffix=" €" />
    </div>
  );
}

// ── Onglet Utilisateurs ─────────────────────────────────────────────────────────
function UsersTab() {
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

  if (isLoading) return <p className="text-gray-500 text-sm py-8">Chargement…</p>;

  return (
    <div className="card overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm text-left">
          <thead className="bg-secondary-100 text-gray-600">
            <tr>
              <th className="px-5 py-3.5 font-semibold">Nom</th>
              <th className="px-5 py-3.5 font-semibold">Email</th>
              <th className="px-5 py-3.5 font-semibold">Rôle</th>
              <th className="px-5 py-3.5 font-semibold">Statut</th>
              <th className="px-5 py-3.5 font-semibold text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-secondary-100">
            {users.map((u) => (
              <tr key={u.id} className="hover:bg-secondary-50 transition-colors">
                <td className="px-5 py-3.5 font-medium text-gray-800">{u.prenom} {u.nom}</td>
                <td className="px-5 py-3.5 text-gray-600">{u.email}</td>
                <td className="px-5 py-3.5">
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${ROLE_BADGE[u.role] || 'bg-gray-100 text-gray-700'}`}>
                    {u.role}
                  </span>
                </td>
                <td className="px-5 py-3.5">
                  {Number(u.est_actif) === 1
                    ? <span className="text-green-600 text-xs font-medium">Actif</span>
                    : <span className="text-red-500 text-xs font-medium">Désactivé</span>}
                </td>
                <td className="px-5 py-3.5 text-right">
                  <button
                    onClick={() => toggle(u.id)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                      Number(u.est_actif) === 1
                        ? 'text-red-600 hover:bg-red-50'
                        : 'text-green-600 hover:bg-green-50'
                    }`}
                  >
                    {Number(u.est_actif) === 1 ? <><UserX size={14} /> Désactiver</> : <><UserCheck size={14} /> Activer</>}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── Onglet Boutiques ──────────────────────────────────────────────────────────
function BoutiquesTab() {
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

  if (isLoading) return <p className="text-gray-500 text-sm py-8">Chargement…</p>;

  return (
    <div className="card overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm text-left">
          <thead className="bg-secondary-100 text-gray-600">
            <tr>
              <th className="px-5 py-3.5 font-semibold">Boutique</th>
              <th className="px-5 py-3.5 font-semibold">Vendeur</th>
              <th className="px-5 py-3.5 font-semibold">Produits</th>
              <th className="px-5 py-3.5 font-semibold">Note</th>
              <th className="px-5 py-3.5 font-semibold">Statut</th>
              <th className="px-5 py-3.5 font-semibold text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-secondary-100">
            {boutiques.map((b) => (
              <tr key={b.id} className="hover:bg-secondary-50 transition-colors">
                <td className="px-5 py-3.5 font-medium text-gray-800">{b.nom}</td>
                <td className="px-5 py-3.5 text-gray-600">{b.vendeur_prenom} {b.vendeur_nom}</td>
                <td className="px-5 py-3.5 text-gray-600">{b.nb_produits}</td>
                <td className="px-5 py-3.5 text-gray-600">{Number(b.note_moyenne).toFixed(1)} ★</td>
                <td className="px-5 py-3.5">
                  {Number(b.est_active) === 1
                    ? <span className="text-green-600 text-xs font-medium">Active</span>
                    : <span className="text-red-500 text-xs font-medium">Suspendue</span>}
                </td>
                <td className="px-5 py-3.5 text-right">
                  <button
                    onClick={() => toggle(b.id)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                      Number(b.est_active) === 1
                        ? 'text-red-600 hover:bg-red-50'
                        : 'text-green-600 hover:bg-green-50'
                    }`}
                  >
                    {Number(b.est_active) === 1 ? 'Suspendre' : 'Réactiver'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── Onglet Avis ─────────────────────────────────────────────────────────────────
function AvisTab() {
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

  if (isLoading) return <p className="text-gray-500 text-sm py-8">Chargement…</p>;
  if (avis.length === 0) return <p className="text-gray-500 text-sm py-8">Aucun avis à modérer.</p>;

  return (
    <div className="space-y-3">
      {avis.map((a) => (
        <div key={a.id} className="card p-4 flex items-start justify-between gap-4">
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
            className="flex items-center gap-1.5 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex-shrink-0"
          >
            <Trash2 size={14} /> Supprimer
          </button>
        </div>
      ))}
    </div>
  );
}

// ── Onglet Catégories ───────────────────────────────────────────────────────────
function CategoriesTab() {
  const queryClient = useQueryClient();
  const [nom, setNom] = useState('');
  const [description, setDescription] = useState('');
  const [parentId, setParentId] = useState('');
  const [editId, setEditId] = useState(null);
  const [editNom, setEditNom] = useState('');

  // L'API renvoie { categories, racines } : les racines alimentent le
  // sélecteur de rattachement (« Objet », « Matériau »…).
  const { data, isLoading } = useQuery({
    queryKey: ['admin-categories'],
    queryFn: async () => (await adminAPI.getCategories()).data.data,
  });

  const categories = data?.categories ?? [];
  const racines = data?.racines ?? [];

  const refresh = () => {
    queryClient.invalidateQueries(['admin-categories']);
    // La liste publique des catégories alimente les filtres produits.
    queryClient.invalidateQueries(['categories']);
  };

  const { mutate: create, isPending: creating } = useMutation({
    mutationFn: () => adminAPI.createCategorie({
      nom: nom.trim(),
      description: description.trim() || null,
      parent_id: parentId || null,
    }),
    onSuccess: () => {
      setNom('');
      setDescription('');
      refresh();
      toast.success('Catégorie créée.');
    },
    onError: (e) => toast.error(e.response?.data?.error || 'Création impossible.'),
  });

  const { mutate: rename } = useMutation({
    mutationFn: ({ id, value }) => adminAPI.updateCategorie(id, { nom: value }),
    onSuccess: () => {
      setEditId(null);
      refresh();
      toast.success('Catégorie renommée.');
    },
    onError: (e) => toast.error(e.response?.data?.error || 'Modification impossible.'),
  });

  const { mutate: remove } = useMutation({
    // 1er appel sans force : le back renvoie 409 + le détail de l'impact si la
    // catégorie est utilisée. On demande alors confirmation avant de forcer.
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
    onSuccess: (res) => {
      if (res === null) return; // annulé par l'utilisateur
      refresh();
      toast.success('Catégorie supprimée.');
    },
    onError: (e) => toast.error(e.response?.data?.error || 'Suppression impossible.'),
  });

  const submit = (e) => {
    e.preventDefault();
    if (nom.trim().length < 2) {
      toast.error('Le nom doit contenir au moins 2 caractères.');
      return;
    }
    create();
  };

  return (
    <div className="space-y-6">
      {/* Formulaire de création */}
      <form onSubmit={submit} className="card p-4">
        <h3 className="text-sm font-semibold text-gray-800 mb-3">Nouvelle catégorie</h3>
        <div className="flex flex-col sm:flex-row gap-2">
          <input
            type="text"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            placeholder="Nom (ex. Céramique)"
            maxLength={100}
            className="input-field flex-1"
          />
          <input
            type="text"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Description (facultatif)"
            maxLength={1000}
            className="input-field flex-1"
          />
          <select
            value={parentId}
            onChange={(e) => setParentId(e.target.value)}
            className="input-field sm:w-44"
          >
            <option value="">Racine</option>
            {racines.map((r) => (
              <option key={r.id} value={r.id}>{r.nom}</option>
            ))}
          </select>
          <button type="submit" disabled={creating} className="btn-primary flex items-center gap-1.5 justify-center">
            <Plus size={16} /> {creating ? 'Ajout…' : 'Ajouter'}
          </button>
        </div>
      </form>

      {/* Liste */}
      {isLoading ? (
        <p className="text-gray-500 text-sm py-8">Chargement…</p>
      ) : categories.length === 0 ? (
        <p className="text-gray-500 text-sm py-8">Aucune catégorie pour le moment.</p>
      ) : (
        <div className="card overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-secondary-50 text-gray-600">
              <tr>
                <th className="px-4 py-3 font-medium">Nom</th>
                <th className="px-4 py-3 font-medium">Groupe</th>
                <th className="px-4 py-3 font-medium">Slug</th>
                <th className="px-4 py-3 font-medium">Produits</th>
                <th className="px-4 py-3 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-secondary-100">
              {categories.map((c) => (
                <tr key={c.id} className="hover:bg-secondary-50/50">
                  <td className="px-4 py-3">
                    {editId === c.id ? (
                      <input
                        type="text"
                        value={editNom}
                        autoFocus
                        onChange={(e) => setEditNom(e.target.value)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') rename({ id: c.id, value: editNom.trim() });
                          if (e.key === 'Escape') setEditId(null);
                        }}
                        className="input-field py-1 text-sm"
                      />
                    ) : (
                      <span className="font-medium text-gray-800">{c.nom}</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {c.parent_nom ? (
                      <span className="text-xs bg-secondary-100 text-gray-600 px-2 py-0.5 rounded-full">
                        {c.parent_nom}
                      </span>
                    ) : (
                      <span className="text-xs text-gray-400">racine</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-gray-500 font-mono text-xs">{c.slug}</td>
                  <td className="px-4 py-3">
                    <span className="inline-flex items-center gap-1 text-gray-600">
                      <Package size={14} /> {c.nb_produits}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-1">
                      {editId === c.id ? (
                        <>
                          <button
                            onClick={() => rename({ id: c.id, value: editNom.trim() })}
                            disabled={editNom.trim().length < 2}
                            className="p-1.5 rounded-lg text-green-600 hover:bg-green-50 disabled:opacity-40"
                            title="Valider"
                          >
                            <Check size={15} />
                          </button>
                          <button
                            onClick={() => setEditId(null)}
                            className="p-1.5 rounded-lg text-gray-500 hover:bg-secondary-100"
                            title="Annuler"
                          >
                            <X size={15} />
                          </button>
                        </>
                      ) : (
                        <>
                          <button
                            onClick={() => { setEditId(c.id); setEditNom(c.nom); }}
                            className="p-1.5 rounded-lg text-gray-500 hover:bg-secondary-100"
                            title="Renommer"
                          >
                            <Pencil size={15} />
                          </button>
                          <button
                            onClick={() => remove(c)}
                            className="p-1.5 rounded-lg text-red-600 hover:bg-red-50"
                            title="Supprimer"
                          >
                            <Trash2 size={15} />
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

// ── Page principale ──────────────────────────────────────────────────────────
export default function AdminPage() {
  const [activeTab, setActiveTab] = useState('overview');

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div className="flex items-center gap-3 mb-8">
        <div className="w-11 h-11 rounded-xl bg-primary flex items-center justify-center">
          <ShieldCheck size={22} className="text-white" />
        </div>
        <div>
          <h1 className="section-title mb-0">Administration</h1>
          <p className="text-gray-500 text-sm">Supervision de la plateforme MarketCraft</p>
        </div>
      </div>

      {/* Onglets */}
      <div className="flex gap-1 border-b border-secondary-200 mb-6 overflow-x-auto">
        {TABS.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key)}
            className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors ${
              activeTab === key
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            <Icon size={16} /> {label}
          </button>
        ))}
      </div>

      {activeTab === 'overview'  && <OverviewTab />}
      {activeTab === 'users'     && <UsersTab />}
      {activeTab === 'boutiques' && <BoutiquesTab />}
      {activeTab === 'categories' && <CategoriesTab />}
      {activeTab === 'avis'      && <AvisTab />}
    </div>
  );
}
