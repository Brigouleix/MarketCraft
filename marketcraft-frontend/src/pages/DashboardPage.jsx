import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  BarChart3, Package, ShoppingBag, Star, Plus, Edit2, Trash2,
  TrendingUp, Store, CheckCircle, Clock, XCircle, AlertCircle,
} from 'lucide-react';
import { productsAPI, ordersAPI, boutiquesAPI, dashboardAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';
import toast from 'react-hot-toast';

const TABS = [
  { key: 'overview', label: 'Vue d\'ensemble', icon: BarChart3 },
  { key: 'products', label: 'Mes produits', icon: Package },
  { key: 'orders', label: 'Mes commandes', icon: ShoppingBag },
  { key: 'boutique', label: 'Ma boutique', icon: Store },
];

const STATUS_CONFIG = {
  en_attente: { label: 'En attente', color: 'text-amber-600 bg-amber-100', icon: Clock },
  confirmee: { label: 'Confirmée', color: 'text-blue-600 bg-blue-100', icon: CheckCircle },
  expediee: { label: 'Expédiée', color: 'text-purple-600 bg-purple-100', icon: TrendingUp },
  livree: { label: 'Livrée', color: 'text-green-600 bg-green-100', icon: CheckCircle },
  annulee: { label: 'Annulée', color: 'text-red-600 bg-red-100', icon: XCircle },
};

const MOCK_STATS = { ca: 3842.50, nb_commandes: 47, nb_produits: 18, note_moyenne: 4.7 };
const MOCK_PRODUCTS = Array.from({ length: 6 }, (_, i) => ({
  id: i + 1, nom: ['Vase céramique', 'Collier argent', 'Panier osier', 'Carnet cuir', 'Bougie cire', 'Savon miel'][i],
  prix: [45, 89, 35, 28, 18, 12][i], stock: [5, 2, 0, 8, 20, 15][i],
  categorie: ['ceramique', 'bijoux', 'textile', 'papeterie', 'cuisine', 'soin'][i],
}));
const MOCK_ORDERS = Array.from({ length: 5 }, (_, i) => ({
  id: 1000 + i, total: [125, 45, 89, 67, 210][i],
  statut: ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'][i],
  created_at: new Date(Date.now() - i * 86400000 * 3).toISOString(),
  client: { nom: ['Alice M.', 'Bernard D.', 'Claire P.', 'David R.', 'Emma L.'][i] },
  nb_articles: [2, 1, 3, 1, 4][i],
}));

function StatCard({ icon: Icon, label, value, color, suffix = '' }) {
  return (
    <div className="card p-5 flex items-center gap-4">
      <div className={`w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ${color}`}>
        <Icon size={22} />
      </div>
      <div>
        <p className="text-xs text-gray-500 font-medium mb-0.5">{label}</p>
        <p className="text-2xl font-bold text-gray-800">{value}{suffix}</p>
      </div>
    </div>
  );
}

function StatusBadge({ statut }) {
  const cfg = STATUS_CONFIG[statut] || { label: statut, color: 'text-gray-600 bg-gray-100', icon: AlertCircle };
  const Icon = cfg.icon;
  return (
    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${cfg.color}`}>
      <Icon size={11} /> {cfg.label}
    </span>
  );
}

function ProductForm({ product, onClose, onSaved }) {
  const [form, setForm] = useState({
    nom: product?.nom || '',
    prix: product?.prix || '',
    stock: product?.stock || '',
    categorie: product?.categorie || '',
    description: product?.description || '',
  });
  const queryClient = useQueryClient();

  const { mutate, isPending } = useMutation({
    mutationFn: () =>
      product?.id ? productsAPI.update(product.id, form) : productsAPI.create(form),
    onSuccess: () => {
      toast.success(product?.id ? 'Produit mis à jour !' : 'Produit créé !');
      queryClient.invalidateQueries(['my-products']);
      onSaved?.();
    },
    onError: () => toast.error('Erreur lors de la sauvegarde.'),
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!form.nom.trim() || !form.prix || !form.stock) {
      toast.error('Remplissez tous les champs obligatoires.');
      return;
    }
    mutate();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">
        <h3 className="font-serif font-bold text-lg text-gray-800 mb-5">
          {product?.id ? 'Modifier le produit' : 'Nouveau produit'}
        </h3>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
            <input value={form.nom} onChange={(e) => setForm((p) => ({ ...p, nom: e.target.value }))}
              className="input-field text-sm" placeholder="Nom du produit" required />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Prix (€) *</label>
              <input type="number" min="0" step="0.01" value={form.prix}
                onChange={(e) => setForm((p) => ({ ...p, prix: e.target.value }))}
                className="input-field text-sm" placeholder="0.00" required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Stock *</label>
              <input type="number" min="0" value={form.stock}
                onChange={(e) => setForm((p) => ({ ...p, stock: e.target.value }))}
                className="input-field text-sm" placeholder="0" required />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
            <input value={form.categorie} onChange={(e) => setForm((p) => ({ ...p, categorie: e.target.value }))}
              className="input-field text-sm" placeholder="ceramique, bijoux…" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea value={form.description}
              onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
              rows={3} className="input-field text-sm resize-none" placeholder="Description du produit…" />
          </div>
          <div className="flex gap-3 pt-2">
            <button type="button" onClick={onClose} className="btn-secondary flex-1">Annuler</button>
            <button type="submit" disabled={isPending} className="btn-primary flex-1">
              {isPending ? 'Sauvegarde…' : 'Sauvegarder'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function DashboardPage() {
  const [activeTab, setActiveTab] = useState('overview');
  const [editingProduct, setEditingProduct] = useState(null);
  const [showProductForm, setShowProductForm] = useState(false);
  const { user } = useAuth();
  const queryClient = useQueryClient();

  const { data: statsData } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: async () => {
      const { data } = await dashboardAPI.getStats();
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });

  const { data: productsData, isLoading: productsLoading } = useQuery({
    queryKey: ['my-products'],
    queryFn: async () => {
      const { data } = await productsAPI.getAll({ my: true });
      return data;
    },
    staleTime: 1000 * 60 * 2,
    enabled: activeTab === 'products',
  });

  const { data: ordersData } = useQuery({
    queryKey: ['my-orders'],
    queryFn: async () => {
      const { data } = await ordersAPI.getAll();
      return data;
    },
    staleTime: 1000 * 60 * 2,
    enabled: activeTab === 'orders',
  });

  const { data: boutiqueData } = useQuery({
    queryKey: ['my-boutique'],
    queryFn: async () => {
      const { data } = await boutiquesAPI.getAll({ my: true });
      return data;
    },
    staleTime: 1000 * 60 * 5,
    enabled: activeTab === 'boutique',
  });

  const stats = statsData || MOCK_STATS;
  const products = productsData?.data || productsData?.products || MOCK_PRODUCTS;
  const orders = ordersData?.data || ordersData?.orders || MOCK_ORDERS;
  const boutique = boutiqueData?.boutique || boutiqueData?.data?.[0] || { nom: 'Ma boutique', description: '', image: '' };

  const deleteMutation = useMutation({
    mutationFn: (id) => productsAPI.delete(id),
    onSuccess: () => {
      toast.success('Produit supprimé.');
      queryClient.invalidateQueries(['my-products']);
    },
    onError: () => toast.error('Erreur lors de la suppression.'),
  });

  const updateOrderStatus = useMutation({
    mutationFn: ({ id, status }) => ordersAPI.updateStatus(id, status),
    onSuccess: () => {
      toast.success('Statut mis à jour.');
      queryClient.invalidateQueries(['my-orders']);
    },
  });

  const [boutiqueForm, setBoutiqueForm] = useState({
    nom: boutique.nom || '',
    description: boutique.description || '',
    image: boutique.image || '',
  });

  const saveBoutiqueMutation = useMutation({
    mutationFn: () =>
      boutique.id
        ? boutiquesAPI.update(boutique.id, boutiqueForm)
        : boutiquesAPI.create(boutiqueForm),
    onSuccess: () => {
      toast.success('Boutique sauvegardée !');
      queryClient.invalidateQueries(['my-boutique']);
    },
    onError: () => toast.error('Erreur de sauvegarde.'),
  });

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Header */}
      <div className="mb-8">
        <h1 className="section-title">Dashboard vendeur</h1>
        <p className="text-gray-500">Bienvenue, {user?.prenom || user?.nom} ! Gérez votre activité.</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-secondary-300 mb-8 overflow-x-auto">
        {TABS.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key)}
            className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors ${
              activeTab === key
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            <Icon size={15} /> {label}
          </button>
        ))}
      </div>

      {/* ── Tab: Overview ─────────────────────────────────────────────────── */}
      {activeTab === 'overview' && (
        <div className="space-y-8">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <StatCard icon={TrendingUp} label="Chiffre d'affaires" value={`${stats.ca?.toFixed(2) || '0.00'} €`} color="bg-green-100 text-green-600" />
            <StatCard icon={ShoppingBag} label="Commandes" value={stats.nb_commandes || 0} color="bg-blue-100 text-blue-600" />
            <StatCard icon={Package} label="Produits" value={stats.nb_produits || 0} color="bg-primary-100 text-primary" />
            <StatCard icon={Star} label="Note moyenne" value={Number(stats.note_moyenne || 0).toFixed(1)} suffix="★" color="bg-amber-100 text-amber-600" />
          </div>

          {/* Recent orders */}
          <div className="card p-6">
            <h2 className="font-serif font-bold text-lg text-gray-800 mb-4">Dernières commandes</h2>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left border-b border-secondary-200">
                    <th className="pb-3 font-semibold text-gray-600">N°</th>
                    <th className="pb-3 font-semibold text-gray-600">Client</th>
                    <th className="pb-3 font-semibold text-gray-600">Total</th>
                    <th className="pb-3 font-semibold text-gray-600">Statut</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-secondary-100">
                  {MOCK_ORDERS.slice(0, 3).map((order) => (
                    <tr key={order.id} className="hover:bg-secondary-50 transition-colors">
                      <td className="py-3 font-mono text-gray-600">#{order.id}</td>
                      <td className="py-3">{order.client.nom}</td>
                      <td className="py-3 font-semibold">{order.total.toFixed(2)} €</td>
                      <td className="py-3"><StatusBadge statut={order.statut} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ── Tab: Products ─────────────────────────────────────────────────── */}
      {activeTab === 'products' && (
        <div>
          <div className="flex justify-between items-center mb-5">
            <h2 className="font-serif font-bold text-xl text-gray-800">Mes produits</h2>
            <button
              onClick={() => { setEditingProduct(null); setShowProductForm(true); }}
              className="btn-primary flex items-center gap-2 text-sm"
            >
              <Plus size={15} /> Ajouter un produit
            </button>
          </div>

          {productsLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="card p-4 animate-pulse flex gap-4">
                  <div className="w-12 h-12 bg-secondary-300 rounded-lg" />
                  <div className="flex-1 space-y-2">
                    <div className="h-4 bg-secondary-300 rounded w-1/3" />
                    <div className="h-3 bg-secondary-300 rounded w-1/4" />
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="card overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-secondary-100">
                  <tr className="text-left">
                    <th className="px-4 py-3 font-semibold text-gray-600">Produit</th>
                    <th className="px-4 py-3 font-semibold text-gray-600">Catégorie</th>
                    <th className="px-4 py-3 font-semibold text-gray-600">Prix</th>
                    <th className="px-4 py-3 font-semibold text-gray-600">Stock</th>
                    <th className="px-4 py-3 font-semibold text-gray-600">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-secondary-100">
                  {products.map((product) => (
                    <tr key={product.id} className="hover:bg-secondary-50 transition-colors">
                      <td className="px-4 py-3 font-medium text-gray-800">{product.nom}</td>
                      <td className="px-4 py-3 text-gray-600 capitalize">{product.categorie || '–'}</td>
                      <td className="px-4 py-3 font-semibold text-primary">{Number(product.prix).toFixed(2)} €</td>
                      <td className="px-4 py-3">
                        <span className={`font-medium ${product.stock > 0 ? 'text-green-600' : 'text-red-500'}`}>
                          {product.stock > 0 ? product.stock : 'Épuisé'}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-2">
                          <button
                            onClick={() => { setEditingProduct(product); setShowProductForm(true); }}
                            className="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded transition-colors"
                            title="Modifier"
                          >
                            <Edit2 size={15} />
                          </button>
                          <button
                            onClick={() => {
                              if (window.confirm('Supprimer ce produit ?')) {
                                deleteMutation.mutate(product.id);
                              }
                            }}
                            className="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                            title="Supprimer"
                          >
                            <Trash2 size={15} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {showProductForm && (
            <ProductForm
              product={editingProduct}
              onClose={() => setShowProductForm(false)}
              onSaved={() => setShowProductForm(false)}
            />
          )}
        </div>
      )}

      {/* ── Tab: Orders ───────────────────────────────────────────────────── */}
      {activeTab === 'orders' && (
        <div>
          <h2 className="font-serif font-bold text-xl text-gray-800 mb-5">Mes commandes</h2>
          <div className="card overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-secondary-100">
                <tr className="text-left">
                  <th className="px-4 py-3 font-semibold text-gray-600">N° commande</th>
                  <th className="px-4 py-3 font-semibold text-gray-600">Client</th>
                  <th className="px-4 py-3 font-semibold text-gray-600">Date</th>
                  <th className="px-4 py-3 font-semibold text-gray-600">Articles</th>
                  <th className="px-4 py-3 font-semibold text-gray-600">Total</th>
                  <th className="px-4 py-3 font-semibold text-gray-600">Statut</th>
                  <th className="px-4 py-3 font-semibold text-gray-600">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-secondary-100">
                {orders.map((order) => (
                  <tr key={order.id} className="hover:bg-secondary-50 transition-colors">
                    <td className="px-4 py-3 font-mono text-gray-600">#{order.id}</td>
                    <td className="px-4 py-3">{order.client?.nom || '–'}</td>
                    <td className="px-4 py-3 text-gray-500">
                      {new Date(order.created_at).toLocaleDateString('fr-FR')}
                    </td>
                    <td className="px-4 py-3 text-gray-600">{order.nb_articles || 1}</td>
                    <td className="px-4 py-3 font-semibold">{Number(order.total).toFixed(2)} €</td>
                    <td className="px-4 py-3"><StatusBadge statut={order.statut} /></td>
                    <td className="px-4 py-3">
                      <select
                        value={order.statut}
                        onChange={(e) => updateOrderStatus.mutate({ id: order.id, status: e.target.value })}
                        className="text-xs border border-secondary-300 rounded-lg px-2 py-1 bg-white cursor-pointer focus:outline-none"
                      >
                        {Object.entries(STATUS_CONFIG).map(([val, { label }]) => (
                          <option key={val} value={val}>{label}</option>
                        ))}
                      </select>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── Tab: Boutique ─────────────────────────────────────────────────── */}
      {activeTab === 'boutique' && (
        <div className="max-w-xl">
          <h2 className="font-serif font-bold text-xl text-gray-800 mb-5">Paramètres de ma boutique</h2>
          <div className="card p-6 space-y-4">
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
                placeholder="Décrivez votre boutique, votre savoir-faire…"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">URL de l'image / logo</label>
              <input
                value={boutiqueForm.image}
                onChange={(e) => setBoutiqueForm((p) => ({ ...p, image: e.target.value }))}
                className="input-field"
                placeholder="https://…"
                type="url"
              />
              {boutiqueForm.image && (
                <img
                  src={boutiqueForm.image}
                  alt="Aperçu"
                  className="mt-3 w-full h-40 object-cover rounded-xl border border-secondary-200"
                  onError={(e) => { e.currentTarget.style.display = 'none'; }}
                />
              )}
            </div>
            <button
              onClick={() => saveBoutiqueMutation.mutate()}
              disabled={saveBoutiqueMutation.isPending || !boutiqueForm.nom.trim()}
              className="btn-primary w-full"
            >
              {saveBoutiqueMutation.isPending ? 'Sauvegarde…' : 'Enregistrer les modifications'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
