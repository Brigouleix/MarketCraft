import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  BarChart3, Users, Store, Star, ShieldCheck, Trash2,
  UserCheck, UserX, Package, ShoppingBag,
} from 'lucide-react';
import { adminAPI } from '../services/api';
import toast from 'react-hot-toast';

const TABS = [
  { key: 'overview',   label: "Vue d'ensemble", icon: BarChart3 },
  { key: 'users',      label: 'Utilisateurs',   icon: Users     },
  { key: 'boutiques',  label: 'Boutiques',      icon: Store     },
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
      {activeTab === 'avis'      && <AvisTab />}
    </div>
  );
}
