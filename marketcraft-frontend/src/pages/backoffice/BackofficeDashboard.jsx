import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  LayoutDashboard, Package, ShoppingBag, Store, LogOut,
  TrendingUp, Star, Plus, Edit2, Trash2,
  CheckCircle, Clock, XCircle, AlertCircle, ChevronRight,
} from 'lucide-react';
import toast from 'react-hot-toast';
import { useAuth } from '../../hooks/useAuth';
import { dashboardAPI, vendorAPI, productsAPI, boutiquesAPI, ordersAPI } from '../../services/api';

// ─── Navigation ──────────────────────────────────────────────────────────────

const NAV = [
  { key: 'overview',  label: 'Vue d\'ensemble', icon: LayoutDashboard },
  { key: 'products',  label: 'Mes produits',     icon: Package },
  { key: 'orders',    label: 'Commandes',         icon: ShoppingBag },
  { key: 'boutique',  label: 'Ma boutique',       icon: Store },
];

// ─── Status config ────────────────────────────────────────────────────────────

const STATUS = {
  en_attente:   { label: 'En attente',    color: 'text-amber-700 bg-amber-100',  icon: Clock },
  confirmee:    { label: 'Confirmée',     color: 'text-blue-700 bg-blue-100',    icon: CheckCircle },
  en_preparation: { label: 'Préparation', color: 'text-indigo-700 bg-indigo-100', icon: Clock },
  expediee:     { label: 'Expédiée',      color: 'text-purple-700 bg-purple-100', icon: TrendingUp },
  livree:       { label: 'Livrée',        color: 'text-green-700 bg-green-100',  icon: CheckCircle },
  annulee:      { label: 'Annulée',       color: 'text-red-700 bg-red-100',      icon: XCircle },
};

// ─── Small helpers ───────────────────────────────────────────────────────────

function StatusBadge({ statut }) {
  const cfg = STATUS[statut] ?? { label: statut, color: 'text-gray-600 bg-gray-100', icon: AlertCircle };
  const Icon = cfg.icon;
  return (
    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${cfg.color}`}>
      <Icon size={11} /> {cfg.label}
    </span>
  );
}

function StatCard({ icon: Icon, label, value, color }) {
  return (
    <div className="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4 border border-gray-100">
      <div className={`w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ${color}`}>
        <Icon size={22} />
      </div>
      <div>
        <p className="text-xs text-gray-500 font-medium mb-0.5">{label}</p>
        <p className="text-2xl font-bold text-gray-800">{value}</p>
      </div>
    </div>
  );
}

// ─── Product Form Modal ───────────────────────────────────────────────────────

function ProductForm({ product, boutiqueId, onClose }) {
  const queryClient = useQueryClient();
  const [form, setForm] = useState({
    nom:         product?.nom         ?? '',
    prix:        product?.prix        ?? '',
    stock:       product?.stock       ?? '',
    description: product?.description ?? '',
    boutique_id: product?.boutique_id ?? boutiqueId ?? '',
  });

  const set = (key) => (e) => setForm((p) => ({ ...p, [key]: e.target.value }));

  const { mutate, isPending } = useMutation({
    mutationFn: () =>
      product?.id ? productsAPI.update(product.id, form) : productsAPI.create(form),
    onSuccess: () => {
      toast.success(product?.id ? 'Produit mis à jour !' : 'Produit créé !');
      queryClient.invalidateQueries({ queryKey: ['bo-products'] });
      onClose();
    },
    onError: (err) => {
      const msg = err.response?.data?.error || 'Erreur lors de la sauvegarde.';
      toast.error(msg);
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!form.nom.trim() || !form.prix || form.stock === '') {
      toast.error('Nom, prix et stock sont obligatoires.');
      return;
    }
    if (!form.boutique_id) {
      toast.error('Aucune boutique associée. Créez d\'abord une boutique.');
      return;
    }
    mutate();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">
        <h3 className="font-semibold text-lg text-gray-800 mb-5">
          {product?.id ? 'Modifier le produit' : 'Nouveau produit'}
        </h3>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
            <input value={form.nom} onChange={set('nom')} className="input-field text-sm" placeholder="Nom du produit" required />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Prix (€) *</label>
              <input type="number" min="0" step="0.01" value={form.prix} onChange={set('prix')} className="input-field text-sm" placeholder="0.00" required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Stock *</label>
              <input type="number" min="0" value={form.stock} onChange={set('stock')} className="input-field text-sm" placeholder="0" required />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea value={form.description} onChange={set('description')} rows={3} className="input-field text-sm resize-none" placeholder="Description…" />
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="btn-secondary flex-1 text-sm">Annuler</button>
            <button type="submit" disabled={isPending} className="btn-primary flex-1 text-sm">
              {isPending ? 'Sauvegarde…' : 'Sauvegarder'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ─── Main dashboard ───────────────────────────────────────────────────────────

export default function BackofficeDashboard() {
  const { user, logout } = useAuth();
  const navigate         = useNavigate();
  const queryClient      = useQueryClient();

  const [section, setSection]             = useState('overview');
  const [editingProduct, setEditingProduct] = useState(null);
  const [showProductForm, setShowProductForm] = useState(false);
  const [boutiqueForm, setBoutiqueForm]   = useState({ nom: '', description: '', image: '' });

  // ── Queries ──

  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ['bo-stats'],
    queryFn: async () => {
      const { data } = await dashboardAPI.getStats();
      return data.data;
    },
    staleTime: 1000 * 60 * 5,
    retry: false,
  });

  const { data: productsData, isLoading: productsLoading } = useQuery({
    queryKey: ['bo-products'],
    queryFn: async () => {
      const { data } = await vendorAPI.getProducts();
      return data;
    },
    enabled: section === 'products',
    staleTime: 1000 * 60 * 2,
    retry: false,
  });

  const { data: ordersData, isLoading: ordersLoading } = useQuery({
    queryKey: ['bo-orders'],
    queryFn: async () => {
      const { data } = await vendorAPI.getOrders();
      return data;
    },
    enabled: section === 'orders',
    staleTime: 1000 * 60 * 2,
    retry: false,
  });

  const { data: boutiqueData } = useQuery({
    queryKey: ['bo-boutique'],
    queryFn: async () => {
      const { data } = await boutiquesAPI.getAll({ my: true });
      return data;
    },
    enabled: section === 'boutique',
    staleTime: 1000 * 60 * 5,
    onSuccess: (data) => {
      const b = data?.data?.[0] || data?.boutiques?.[0];
      if (b) setBoutiqueForm({ nom: b.nom || '', description: b.description || '', image: b.image || '' });
    },
    retry: false,
  });

  // ── Mutations ──

  const deleteProduct = useMutation({
    mutationFn: (id) => productsAPI.delete(id),
    onSuccess: () => {
      toast.success('Produit supprimé.');
      queryClient.invalidateQueries({ queryKey: ['bo-products'] });
    },
    onError: () => toast.error('Erreur lors de la suppression.'),
  });

  const updateStatus = useMutation({
    mutationFn: ({ id, status }) => ordersAPI.updateStatus(id, status),
    onSuccess: () => {
      toast.success('Statut mis à jour.');
      queryClient.invalidateQueries({ queryKey: ['bo-orders'] });
    },
  });

  const saveBoutique = useMutation({
    mutationFn: () => {
      const b = boutiqueData?.data?.[0] || boutiqueData?.boutiques?.[0];
      return b
        ? boutiquesAPI.update(b.id, boutiqueForm)
        : boutiquesAPI.create(boutiqueForm);
    },
    onSuccess: () => {
      toast.success('Boutique sauvegardée !');
      queryClient.invalidateQueries({ queryKey: ['bo-boutique'] });
    },
    onError: () => toast.error('Erreur de sauvegarde.'),
  });

  // ── Derived data ──

  const stats    = statsData || {};
  const products = productsData?.data || [];
  const orders   = ordersData?.data   || [];
  const boutique = boutiqueData?.data?.[0] || boutiqueData?.boutiques?.[0];

  const handleLogout = async () => {
    await logout();
    navigate('/backoffice/login', { replace: true });
  };

  const currentNav = NAV.find((n) => n.key === section) || NAV[0];

  return (
    <div className="min-h-screen bg-gray-50 flex">
      {/* ── Sidebar ──────────────────────────────────────────────────────── */}
      <aside className="w-60 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
        {/* Brand */}
        <div className="px-6 py-5 border-b border-gray-100">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
              <Store size={16} className="text-white" />
            </div>
            <div>
              <p className="text-sm font-bold text-gray-900 leading-none">MarketCraft</p>
              <p className="text-[10px] text-gray-400 mt-0.5">Espace vendeur</p>
            </div>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 px-3 py-4 space-y-1">
          {NAV.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              onClick={() => setSection(key)}
              className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all ${
                section === key
                  ? 'bg-primary text-white shadow-sm'
                  : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
              }`}
            >
              <Icon size={16} />
              {label}
              {section === key && <ChevronRight size={14} className="ml-auto opacity-70" />}
            </button>
          ))}
        </nav>

        {/* User + logout */}
        <div className="px-3 py-4 border-t border-gray-100">
          <div className="flex items-center gap-2.5 px-3 py-2 mb-2 rounded-lg bg-gray-50">
            <div className="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
              {(user?.prenom || user?.nom || 'V')[0].toUpperCase()}
            </div>
            <div className="min-w-0">
              <p className="text-xs font-semibold text-gray-800 truncate">
                {user?.prenom} {user?.nom}
              </p>
              <p className="text-[10px] text-gray-400 truncate">{user?.email}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
          >
            <LogOut size={15} />
            Déconnexion
          </button>
        </div>
      </aside>

      {/* ── Main ─────────────────────────────────────────────────────────── */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Topbar */}
        <header className="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
          <h1 className="text-base font-semibold text-gray-800">{currentNav.label}</h1>
          <span className="text-xs text-gray-400">{new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</span>
        </header>

        {/* Content */}
        <main className="flex-1 p-6 overflow-auto">

          {/* ── Overview ──────────────────────────────────────────────── */}
          {section === 'overview' && (
            <div className="space-y-6">
              {statsLoading ? (
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                  {[...Array(4)].map((_, i) => (
                    <div key={i} className="bg-white rounded-xl p-5 h-24 animate-pulse border border-gray-100" />
                  ))}
                </div>
              ) : (
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                  <StatCard icon={TrendingUp} label="Chiffre d'affaires" value={`${Number(stats.ca || 0).toFixed(2)} €`} color="bg-emerald-100 text-emerald-600" />
                  <StatCard icon={ShoppingBag} label="Commandes" value={stats.nb_commandes ?? 0} color="bg-blue-100 text-blue-600" />
                  <StatCard icon={Package} label="Produits actifs" value={stats.nb_produits ?? 0} color="bg-violet-100 text-violet-600" />
                  <StatCard icon={Star} label="Note moyenne" value={`${Number(stats.note_moyenne || 0).toFixed(1)} ★`} color="bg-amber-100 text-amber-600" />
                </div>
              )}

              {/* Recent orders */}
              <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100">
                  <h2 className="font-semibold text-gray-800">Dernières commandes</h2>
                </div>
                {statsLoading ? (
                  <div className="p-6 space-y-3">
                    {[...Array(3)].map((_, i) => (
                      <div key={i} className="h-8 bg-gray-100 rounded animate-pulse" />
                    ))}
                  </div>
                ) : (
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-left">
                      <tr>
                        {['N°', 'Client', 'Total', 'Statut'].map((h) => (
                          <th key={h} className="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                      {(stats.recent_orders || []).length === 0 ? (
                        <tr>
                          <td colSpan={4} className="px-6 py-8 text-center text-gray-400 text-sm">
                            Aucune commande pour l'instant.
                          </td>
                        </tr>
                      ) : (
                        (stats.recent_orders || []).map((o) => (
                          <tr key={o.id} className="hover:bg-gray-50 transition-colors">
                            <td className="px-6 py-3 font-mono text-gray-500">#{o.id}</td>
                            <td className="px-6 py-3 text-gray-700">{o.client_prenom} {o.client_nom}</td>
                            <td className="px-6 py-3 font-semibold text-gray-800">{Number(o.total).toFixed(2)} €</td>
                            <td className="px-6 py-3"><StatusBadge statut={o.statut} /></td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}

          {/* ── Products ──────────────────────────────────────────────── */}
          {section === 'products' && (
            <div>
              <div className="flex justify-between items-center mb-5">
                <p className="text-sm text-gray-500">{products.length} produit(s)</p>
                <button
                  onClick={() => { setEditingProduct(null); setShowProductForm(true); }}
                  className="btn-primary flex items-center gap-2 text-sm"
                >
                  <Plus size={15} /> Nouveau produit
                </button>
              </div>

              <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                {productsLoading ? (
                  <div className="p-6 space-y-3">
                    {[...Array(5)].map((_, i) => (
                      <div key={i} className="h-10 bg-gray-100 rounded animate-pulse" />
                    ))}
                  </div>
                ) : products.length === 0 ? (
                  <div className="py-16 text-center text-gray-400">
                    <Package size={40} className="mx-auto mb-3 opacity-30" />
                    <p className="text-sm">Aucun produit. Ajoutez votre premier produit !</p>
                  </div>
                ) : (
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-left">
                      <tr>
                        {['Produit', 'Boutique', 'Prix', 'Stock', 'Actions'].map((h) => (
                          <th key={h} className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                      {products.map((p) => (
                        <tr key={p.id} className="hover:bg-gray-50 transition-colors">
                          <td className="px-5 py-3 font-medium text-gray-800">{p.nom}</td>
                          <td className="px-5 py-3 text-gray-500">{p.boutique_nom || '–'}</td>
                          <td className="px-5 py-3 font-semibold text-primary">{Number(p.prix).toFixed(2)} €</td>
                          <td className="px-5 py-3">
                            <span className={`font-medium ${p.stock > 0 ? 'text-emerald-600' : 'text-red-500'}`}>
                              {p.stock > 0 ? p.stock : 'Épuisé'}
                            </span>
                          </td>
                          <td className="px-5 py-3">
                            <div className="flex gap-1.5">
                              <button
                                onClick={() => { setEditingProduct(p); setShowProductForm(true); }}
                                className="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors"
                                title="Modifier"
                              >
                                <Edit2 size={14} />
                              </button>
                              <button
                                onClick={() => {
                                  if (window.confirm(`Supprimer "${p.nom}" ?`)) {
                                    deleteProduct.mutate(p.id);
                                  }
                                }}
                                className="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                title="Supprimer"
                              >
                                <Trash2 size={14} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>

              {showProductForm && (
                <ProductForm
                  product={editingProduct}
                  boutiqueId={boutique?.id}
                  onClose={() => { setShowProductForm(false); setEditingProduct(null); }}
                />
              )}
            </div>
          )}

          {/* ── Orders ────────────────────────────────────────────────── */}
          {section === 'orders' && (
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
              {ordersLoading ? (
                <div className="p-6 space-y-3">
                  {[...Array(5)].map((_, i) => (
                    <div key={i} className="h-10 bg-gray-100 rounded animate-pulse" />
                  ))}
                </div>
              ) : orders.length === 0 ? (
                <div className="py-16 text-center text-gray-400">
                  <ShoppingBag size={40} className="mx-auto mb-3 opacity-30" />
                  <p className="text-sm">Aucune commande reçue pour l'instant.</p>
                </div>
              ) : (
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 text-left">
                    <tr>
                      {['N°', 'Client', 'Date', 'Articles', 'Total', 'Statut', 'Action'].map((h) => (
                        <th key={h} className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {orders.map((o) => (
                      <tr key={o.id} className="hover:bg-gray-50 transition-colors">
                        <td className="px-5 py-3 font-mono text-gray-500">#{o.id}</td>
                        <td className="px-5 py-3 text-gray-700">{o.client_prenom} {o.client_nom}</td>
                        <td className="px-5 py-3 text-gray-400">
                          {new Date(o.created_at).toLocaleDateString('fr-FR')}
                        </td>
                        <td className="px-5 py-3 text-gray-600">{o.nb_articles}</td>
                        <td className="px-5 py-3 font-semibold text-gray-800">{Number(o.total).toFixed(2)} €</td>
                        <td className="px-5 py-3"><StatusBadge statut={o.statut} /></td>
                        <td className="px-5 py-3">
                          <select
                            value={o.statut}
                            onChange={(e) => updateStatus.mutate({ id: o.id, status: e.target.value })}
                            className="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white cursor-pointer focus:outline-none focus:border-primary"
                          >
                            {Object.entries(STATUS).map(([val, { label }]) => (
                              <option key={val} value={val}>{label}</option>
                            ))}
                          </select>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          )}

          {/* ── Boutique ──────────────────────────────────────────────── */}
          {section === 'boutique' && (
            <div className="max-w-lg">
              <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">Nom de la boutique *</label>
                  <input
                    value={boutiqueForm.nom}
                    onChange={(e) => setBoutiqueForm((p) => ({ ...p, nom: e.target.value }))}
                    className="input-field"
                    placeholder="Ma superbe boutique"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                  <textarea
                    value={boutiqueForm.description}
                    onChange={(e) => setBoutiqueForm((p) => ({ ...p, description: e.target.value }))}
                    rows={4}
                    className="input-field resize-none"
                    placeholder="Décrivez votre boutique et votre savoir-faire…"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">URL du logo</label>
                  <input
                    type="url"
                    value={boutiqueForm.image}
                    onChange={(e) => setBoutiqueForm((p) => ({ ...p, image: e.target.value }))}
                    className="input-field"
                    placeholder="https://…"
                  />
                  {boutiqueForm.image && (
                    <img
                      src={boutiqueForm.image}
                      alt="Aperçu"
                      className="mt-3 w-full h-36 object-cover rounded-xl border border-gray-200"
                      onError={(e) => { e.currentTarget.style.display = 'none'; }}
                    />
                  )}
                </div>
                <button
                  onClick={() => saveBoutique.mutate()}
                  disabled={saveBoutique.isPending || !boutiqueForm.nom.trim()}
                  className="btn-primary w-full"
                >
                  {saveBoutique.isPending ? 'Sauvegarde…' : 'Enregistrer les modifications'}
                </button>
              </div>
            </div>
          )}

        </main>
      </div>
    </div>
  );
}
