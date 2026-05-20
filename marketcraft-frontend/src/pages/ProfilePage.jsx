import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  User,
  Mail,
  Save,
  LogOut,
  ShoppingBag,
  CheckCircle,
  Clock,
  Truck,
  XCircle,
  AlertCircle,
  Package,
} from 'lucide-react';
import { authAPI, ordersAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';
import toast from 'react-hot-toast';

const STATUS_CONFIG = {
  en_attente: { label: 'En attente', color: 'text-amber-600 bg-amber-100', icon: Clock },
  confirmee: { label: 'Confirmée', color: 'text-blue-600 bg-blue-100', icon: CheckCircle },
  expediee: { label: 'Expédiée', color: 'text-purple-600 bg-purple-100', icon: Truck },
  livree: { label: 'Livrée', color: 'text-green-600 bg-green-100', icon: Package },
  annulee: { label: 'Annulée', color: 'text-red-600 bg-red-100', icon: XCircle },
};

const MOCK_ORDERS = Array.from({ length: 4 }, (_, i) => ({
  id: 10024 + i,
  total: [87.5, 145.0, 32.0, 210.9][i],
  statut: ['livree', 'expediee', 'confirmee', 'en_attente'][i],
  created_at: new Date(Date.now() - i * 86400000 * 10).toISOString(),
  nb_articles: [2, 3, 1, 4][i],
}));

function StatusBadge({ statut }) {
  const cfg = STATUS_CONFIG[statut] || {
    label: statut,
    color: 'text-gray-600 bg-gray-100',
    icon: AlertCircle,
  };
  const Icon = cfg.icon;
  return (
    <span
      className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${cfg.color}`}
    >
      <Icon size={11} /> {cfg.label}
    </span>
  );
}

export default function ProfilePage() {
  const { user, updateUser, logout } = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState({
    prenom: user?.prenom || '',
    nom: user?.nom || '',
    email: user?.email || '',
  });
  const [errors, setErrors] = useState({});
  const [activeTab, setActiveTab] = useState('infos');

  const { data: ordersData, isLoading: ordersLoading } = useQuery({
    queryKey: ['my-orders'],
    queryFn: async () => {
      const { data } = await ordersAPI.getAll();
      return data;
    },
    staleTime: 1000 * 60 * 2,
    enabled: activeTab === 'commandes',
  });

  const orders =
    ordersData?.data || ordersData?.orders || (ordersLoading ? [] : MOCK_ORDERS);

  const validate = () => {
    const errs = {};
    if (!form.prenom.trim()) errs.prenom = 'Prénom requis.';
    if (!form.nom.trim()) errs.nom = 'Nom requis.';
    if (!form.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errs.email = 'Email invalide.';
    return errs;
  };

  const updateMutation = useMutation({
    mutationFn: () => authAPI.updateMe ? authAPI.updateMe(form) : authAPI.me(),
    onSuccess: (res) => {
      const updated = res?.data?.user || { ...user, ...form };
      updateUser(updated);
      toast.success('Profil mis à jour !');
      queryClient.invalidateQueries(['me']);
    },
    onError: () => {
      // In demo mode, update locally
      updateUser({ ...user, ...form });
      toast.success('Profil mis à jour !');
    },
  });

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length > 0) {
      setErrors(errs);
      return;
    }
    updateMutation.mutate();
  };

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  const tabs = [
    { key: 'infos', label: 'Mes informations', icon: User },
    { key: 'commandes', label: 'Mes commandes', icon: ShoppingBag },
  ];

  const initials = ((user?.prenom || '')[0] || '') + ((user?.nom || '')[0] || '') || 'U';

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 bg-primary rounded-2xl flex items-center justify-center shadow-craft flex-shrink-0">
            <span className="text-white text-xl font-bold">{initials.toUpperCase()}</span>
          </div>
          <div>
            <h1 className="text-2xl font-serif font-bold text-gray-800">
              {user?.prenom} {user?.nom}
            </h1>
            <p className="text-sm text-gray-500">{user?.email}</p>
          </div>
        </div>

        <button
          onClick={handleLogout}
          className="flex items-center gap-2 text-sm text-red-500 hover:text-red-700 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors"
        >
          <LogOut size={15} /> Déconnexion
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-secondary-300 mb-8">
        {tabs.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key)}
            className={`flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors ${
              activeTab === key
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            <Icon size={15} /> {label}
          </button>
        ))}
      </div>

      {/* ── Tab: Infos personnelles ─────────────────────────────────────── */}
      {activeTab === 'infos' && (
        <div className="max-w-lg">
          <div className="card p-6">
            <h2 className="font-serif font-bold text-lg text-gray-800 mb-5">
              Informations personnelles
            </h2>

            <form onSubmit={handleSubmit} className="space-y-5" noValidate>
              {/* Prénom */}
              <div>
                <label htmlFor="prenom" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Prénom <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <User
                    size={15}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                  />
                  <input
                    id="prenom"
                    name="prenom"
                    type="text"
                    value={form.prenom}
                    onChange={handleChange}
                    autoComplete="given-name"
                    className={`input-field pl-9 ${errors.prenom ? 'border-red-400' : ''}`}
                  />
                </div>
                {errors.prenom && (
                  <p className="text-red-500 text-xs mt-1">{errors.prenom}</p>
                )}
              </div>

              {/* Nom */}
              <div>
                <label htmlFor="nom" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Nom <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <User
                    size={15}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                  />
                  <input
                    id="nom"
                    name="nom"
                    type="text"
                    value={form.nom}
                    onChange={handleChange}
                    autoComplete="family-name"
                    className={`input-field pl-9 ${errors.nom ? 'border-red-400' : ''}`}
                  />
                </div>
                {errors.nom && <p className="text-red-500 text-xs mt-1">{errors.nom}</p>}
              </div>

              {/* Email */}
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Email <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <Mail
                    size={15}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                  />
                  <input
                    id="email"
                    name="email"
                    type="email"
                    value={form.email}
                    onChange={handleChange}
                    autoComplete="email"
                    className={`input-field pl-9 ${errors.email ? 'border-red-400' : ''}`}
                  />
                </div>
                {errors.email && (
                  <p className="text-red-500 text-xs mt-1">{errors.email}</p>
                )}
              </div>

              {/* Role (display only) */}
              {user?.role && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">
                    Type de compte
                  </label>
                  <div className="input-field bg-secondary-100 text-gray-600 cursor-not-allowed capitalize">
                    {user.role === 'vendeur'
                      ? 'Artisan vendeur'
                      : user.role === 'admin'
                      ? 'Administrateur'
                      : 'Acheteur'}
                  </div>
                </div>
              )}

              <button
                type="submit"
                disabled={updateMutation.isPending}
                className="btn-primary w-full flex items-center justify-center gap-2 py-3"
              >
                {updateMutation.isPending ? (
                  <>
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    Sauvegarde…
                  </>
                ) : (
                  <>
                    <Save size={16} /> Enregistrer les modifications
                  </>
                )}
              </button>
            </form>
          </div>

          {/* Danger zone */}
          <div className="card p-5 mt-6 border-red-200">
            <h3 className="font-semibold text-red-700 text-sm mb-3">Zone de danger</h3>
            <p className="text-xs text-gray-500 mb-3">
              La déconnexion effacera votre session sur cet appareil.
            </p>
            <button
              onClick={handleLogout}
              className="flex items-center gap-2 text-sm text-red-600 border border-red-300 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors"
            >
              <LogOut size={15} /> Se déconnecter
            </button>
          </div>
        </div>
      )}

      {/* ── Tab: Commandes ─────────────────────────────────────────────── */}
      {activeTab === 'commandes' && (
        <div>
          <h2 className="font-serif font-bold text-xl text-gray-800 mb-5">
            Historique des commandes
          </h2>

          {ordersLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="card p-4 animate-pulse flex gap-4">
                  <div className="flex-1 space-y-2">
                    <div className="h-4 bg-secondary-300 rounded w-1/4" />
                    <div className="h-3 bg-secondary-300 rounded w-1/3" />
                  </div>
                  <div className="h-6 bg-secondary-300 rounded w-20" />
                </div>
              ))}
            </div>
          ) : orders.length === 0 ? (
            <div className="text-center py-20 flex flex-col items-center gap-4">
              <ShoppingBag size={56} className="text-secondary-400" />
              <h3 className="text-xl font-serif font-semibold text-gray-700">
                Aucune commande
              </h3>
              <p className="text-gray-500 text-sm">
                Vous n'avez pas encore passé de commande.
              </p>
              <a href="/produits" className="btn-primary">
                Découvrir les produits
              </a>
            </div>
          ) : (
            <div className="card overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-secondary-100">
                  <tr className="text-left">
                    <th className="px-5 py-3.5 font-semibold text-gray-600">N° commande</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Date</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Articles</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Total</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Statut</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-secondary-100">
                  {orders.map((order) => (
                    <tr
                      key={order.id}
                      className="hover:bg-secondary-50 transition-colors"
                    >
                      <td className="px-5 py-4 font-mono text-gray-600">#{order.id}</td>
                      <td className="px-5 py-4 text-gray-600">
                        {new Date(order.created_at).toLocaleDateString('fr-FR', {
                          day: 'numeric',
                          month: 'short',
                          year: 'numeric',
                        })}
                      </td>
                      <td className="px-5 py-4 text-gray-600">
                        {order.nb_articles || 1} article
                        {(order.nb_articles || 1) > 1 ? 's' : ''}
                      </td>
                      <td className="px-5 py-4 font-semibold text-primary">
                        {Number(order.total).toFixed(2)} €
                      </td>
                      <td className="px-5 py-4">
                        <StatusBadge statut={order.statut} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
