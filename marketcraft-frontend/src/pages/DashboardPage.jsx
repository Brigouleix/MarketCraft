import React, { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  BarChart3, Package, ShoppingBag, Star, Plus, Edit2, Trash2,
  TrendingUp, Store, CheckCircle, Clock, XCircle, AlertCircle,
  Upload, X, Image as ImageIcon,
} from 'lucide-react';
import { productsAPI, ordersAPI, boutiquesAPI, dashboardAPI, uploadAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';
import toast from 'react-hot-toast';

const TABS = [
  { key: 'overview',  label: "Vue d'ensemble", icon: BarChart3  },
  { key: 'products',  label: 'Mes produits',   icon: Package    },
  { key: 'orders',    label: 'Mes commandes',  icon: ShoppingBag},
  { key: 'boutique',  label: 'Ma boutique',    icon: Store      },
];

const STATUS_CONFIG = {
  en_attente:     { label: 'En attente',    color: 'text-amber-600 bg-amber-100',   icon: Clock        },
  confirmee:      { label: 'Confirmée',     color: 'text-blue-600 bg-blue-100',     icon: CheckCircle  },
  en_preparation: { label: 'En préparation',color: 'text-indigo-600 bg-indigo-100', icon: Package      },
  expediee:       { label: 'Expédiée',      color: 'text-purple-600 bg-purple-100', icon: TrendingUp   },
  livree:         { label: 'Livrée',        color: 'text-green-600 bg-green-100',   icon: CheckCircle  },
  annulee:        { label: 'Annulée',       color: 'text-red-600 bg-red-100',       icon: XCircle      },
};

// ── Composants utilitaires ───────────────────────────────────────────────────

function StatCard({ icon: Icon, label, value, sub, color }) {
  return (
    <div className="card p-5 flex items-center gap-4">
      <div className={`w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ${color}`}>
        <Icon size={22} />
      </div>
      <div>
        <p className="text-xs text-gray-500 font-medium mb-0.5">{label}</p>
        <p className="text-2xl font-bold text-gray-800">{value}</p>
        {sub && <p className="text-xs text-gray-400 mt-0.5">{sub}</p>}
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

// ── Upload d'image ───────────────────────────────────────────────────────────

function ImageUploader({ value, onChange, label = 'Image' }) {
  const inputRef = useRef(null);
  const [uploading, setUploading] = useState(false);

  const handleFile = async (file) => {
    if (!file) return;
    setUploading(true);
    try {
      const { data } = await uploadAPI.image(file);
      onChange(data.url);
      toast.success('Image uploadée !');
    } catch {
      toast.error("Erreur d'upload. Max 5 Mo, formats : jpg, png, webp.");
    } finally {
      setUploading(false);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    handleFile(e.dataTransfer.files[0]);
  };

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
      <div
        onDrop={handleDrop}
        onDragOver={(e) => e.preventDefault()}
        onClick={() => inputRef.current?.click()}
        className="border-2 border-dashed border-secondary-400 rounded-xl p-4 text-center cursor-pointer
                   hover:border-primary hover:bg-primary-50 transition-colors"
      >
        {value ? (
          <div className="relative">
            <img src={value} alt="Aperçu" className="w-full h-36 object-cover rounded-lg" />
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); onChange(''); }}
              className="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600"
            >
              <X size={12} />
            </button>
          </div>
        ) : uploading ? (
          <div className="h-36 flex items-center justify-center">
            <div className="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full" />
          </div>
        ) : (
          <div className="h-36 flex flex-col items-center justify-center gap-2 text-gray-400">
            <Upload size={28} />
            <p className="text-sm">Glisser une image ou <span className="text-primary font-medium">parcourir</span></p>
            <p className="text-xs">JPG, PNG, WebP — max 5 Mo</p>
          </div>
        )}
      </div>
      <input ref={inputRef} type="file" accept="image/*" className="hidden"
        onChange={(e) => handleFile(e.target.files[0])} />
    </div>
  );
}

// Upload multiple pour galerie produit
function MultiImageUploader({ value = [], onChange }) {
  const inputRef = useRef(null);
  const [uploading, setUploading] = useState(false);

  const handleFiles = async (files) => {
    if (!files?.length) return;
    setUploading(true);
    try {
      const fileArr = Array.from(files).slice(0, 5 - value.length);
      const { data } = await uploadAPI.images(fileArr);
      onChange([...value, ...data.urls]);
      toast.success(`${data.urls.length} image(s) uploadée(s) !`);
    } catch {
      toast.error("Erreur d'upload.");
    } finally {
      setUploading(false);
    }
  };

  const remove = (i) => onChange(value.filter((_, idx) => idx !== i));

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">
        Photos du produit <span className="text-gray-400 font-normal">({value.length}/5)</span>
      </label>
      <div className="grid grid-cols-3 gap-2 mb-2">
        {value.map((url, i) => (
          <div key={i} className="relative aspect-square">
            <img src={url} alt={`img-${i}`} className="w-full h-full object-cover rounded-lg border border-secondary-200" />
            <button type="button" onClick={() => remove(i)}
              className="absolute top-1 right-1 bg-red-500 text-white rounded-full p-0.5 hover:bg-red-600">
              <X size={10} />
            </button>
          </div>
        ))}
        {value.length < 5 && (
          <button type="button" onClick={() => inputRef.current?.click()}
            className="aspect-square border-2 border-dashed border-secondary-400 rounded-lg
                       flex flex-col items-center justify-center gap-1 text-gray-400
                       hover:border-primary hover:text-primary transition-colors">
            {uploading ? (
              <div className="animate-spin w-5 h-5 border-2 border-primary border-t-transparent rounded-full" />
            ) : (
              <>
                <ImageIcon size={20} />
                <span className="text-xs">Ajouter</span>
              </>
            )}
          </button>
        )}
      </div>
      <input ref={inputRef} type="file" accept="image/*" multiple className="hidden"
        onChange={(e) => handleFiles(e.target.files)} />
    </div>
  );
}

// ── Formulaire produit ───────────────────────────────────────────────────────

function ProductForm({ product, boutiqueId, onClose, onSaved }) {
  const [form, setForm] = useState({
    nom:         product?.nom         || '',
    prix:        product?.prix        || '',
    stock:       product?.stock       || '',
    categorie:   product?.categorie   || '',
    description: product?.description || '',
    images:      product?.images      ? (typeof product.images === 'string' ? JSON.parse(product.images) : product.images) : [],
    boutique_id: product?.boutique_id || boutiqueId || '',
  });
  const queryClient = useQueryClient();

  const up = (k) => (v) => setForm((f) => ({ ...f, [k]: v }));

  const { mutate, isPending } = useMutation({
    mutationFn: () => {
      const payload = { ...form, prix: parseFloat(form.prix), stock: parseInt(form.stock) };
      return product?.id ? productsAPI.update(product.id, payload) : productsAPI.create(payload);
    },
    onSuccess: () => {
      toast.success(product?.id ? 'Produit mis à jour !' : 'Produit créé !');
      queryClient.invalidateQueries(['my-products']);
      onSaved?.();
    },
    onError: (err) => toast.error(err.response?.data?.error || 'Erreur lors de la sauvegarde.'),
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!form.nom.trim() || !form.prix || !form.stock) {
      toast.error('Remplissez tous les champs obligatoires.'); return;
    }
    mutate();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 z-10 my-4">
        <div className="flex items-center justify-between mb-5">
          <h3 className="font-serif font-bold text-lg text-gray-800">
            {product?.id ? 'Modifier le produit' : 'Nouveau produit'}
          </h3>
          <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600"><X size={20} /></button>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
            <input value={form.nom} onChange={(e) => up('nom')(e.target.value)}
              className="input-field text-sm" placeholder="Nom du produit" required />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Prix (€) *</label>
              <input type="number" min="0" step="0.01" value={form.prix}
                onChange={(e) => up('prix')(e.target.value)}
                className="input-field text-sm" placeholder="0.00" required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Stock *</label>
              <input type="number" min="0" value={form.stock}
                onChange={(e) => up('stock')(e.target.value)}
                className="input-field text-sm" placeholder="0" required />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
            <input value={form.categorie} onChange={(e) => up('categorie')(e.target.value)}
              className="input-field text-sm" placeholder="ceramique, bijoux…" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea value={form.description} onChange={(e) => up('description')(e.target.value)}
              rows={3} className="input-field text-sm resize-none" placeholder="Description du produit…" />
          </div>

          {/* Upload images */}
          <MultiImageUploader value={form.images} onChange={(imgs) => up('images')(imgs)} />

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

// ── Page principale ──────────────────────────────────────────────────────────

export default function DashboardPage() {
  const [activeTab, setActiveTab] = useState('overview');
  const [editingProduct, setEditingProduct] = useState(null);
  const [showProductForm, setShowProductForm] = useState(false);
  const { user } = useAuth();
  const queryClient = useQueryClient();

  // Stats réelles
  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: async () => {
      const { data } = await dashboardAPI.getVendeurStats();
      return data.data ?? data;
    },
    staleTime: 1000 * 60 * 3,
    retry: 1,
  });

  const { data: productsData, isLoading: productsLoading } = useQuery({
    queryKey: ['my-products'],
    queryFn: async () => {
      const { data } = await productsAPI.getAll({ my: true });
      return data.data ?? data.products ?? data;
    },
    staleTime: 1000 * 60 * 2,
    enabled: activeTab === 'products',
    retry: 1,
  });

  const { data: ordersData, isLoading: ordersLoading } = useQuery({
    queryKey: ['my-orders'],
    queryFn: async () => {
      const { data } = await ordersAPI.getAll();
      return data.data ?? data.orders ?? data;
    },
    staleTime: 1000 * 60 * 2,
    enabled: activeTab === 'orders',
    retry: 1,
  });

  const { data: boutiqueData } = useQuery({
    queryKey: ['my-boutique'],
    queryFn: async () => {
      const { data } = await boutiquesAPI.getAll({ my: true });
      return data.boutique ?? data.data?.[0] ?? data[0] ?? null;
    },
    staleTime: 1000 * 60 * 5,
    enabled: activeTab === 'boutique',
    retry: 1,
  });

  const stats    = statsData    || {};
  const products = Array.isArray(productsData) ? productsData : [];
  const orders   = Array.isArray(ordersData)   ? ordersData   : [];
  const boutique = boutiqueData || {};

  const [boutiqueForm, setBoutiqueForm] = useState({ nom: '', description: '', image: '' });
  const bUp = (k) => (v) => setBoutiqueForm((f) => ({ ...f, [k]: v }));

  // Sync boutiqueForm quand boutiqueData arrive
  React.useEffect(() => {
    if (boutiqueData) {
      setBoutiqueForm({
        nom:         boutiqueData.nom         || '',
        description: boutiqueData.description || '',
        image:       boutiqueData.image        || '',
      });
    }
  }, [boutiqueData]);

  const deleteMutation = useMutation({
    mutationFn: (id) => productsAPI.delete(id),
    onSuccess: () => { toast.success('Produit supprimé.'); queryClient.invalidateQueries(['my-products']); },
    onError:   () => toast.error('Erreur lors de la suppression.'),
  });

  const updateOrderStatus = useMutation({
    mutationFn: ({ id, status }) => ordersAPI.updateStatus(id, status),
    onSuccess: () => { toast.success('Statut mis à jour.'); queryClient.invalidateQueries(['my-orders']); },
  });

  const saveBoutiqueMutation = useMutation({
    mutationFn: () =>
      boutique.id ? boutiquesAPI.update(boutique.id, boutiqueForm) : boutiquesAPI.create(boutiqueForm),
    onSuccess: () => { toast.success('Boutique sauvegardée !'); queryClient.invalidateQueries(['my-boutique']); },
    onError:   () => toast.error('Erreur de sauvegarde.'),
  });

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div className="mb-8">
        <h1 className="section-title">Dashboard vendeur</h1>
        <p className="text-gray-500">Bienvenue, {user?.prenom || user?.nom} ! Gérez votre activité.</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-secondary-300 mb-8 overflow-x-auto">
        {TABS.map(({ key, label, icon: Icon }) => (
          <button key={key} onClick={() => setActiveTab(key)}
            className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors ${
              activeTab === key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}>
            <Icon size={15} /> {label}
          </button>
        ))}
      </div>

      {/* ── Overview ─────────────────────────────────────────────────────── */}
      {activeTab === 'overview' && (
        <div className="space-y-8">
          {statsLoading ? (
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-5">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="card p-5 animate-pulse h-24" />
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-5">
              <StatCard icon={TrendingUp}  label="Chiffre d'affaires" value={`${Number(stats.ca || 0).toFixed(2)} €`}               color="bg-green-100 text-green-600"  sub="Hors annulations" />
              <StatCard icon={ShoppingBag} label="Commandes"          value={stats.nb_commandes || 0}                                color="bg-blue-100 text-blue-600"    />
              <StatCard icon={Package}     label="Produits actifs"    value={stats.nb_produits  || 0}                                color="bg-primary-100 text-primary"  />
              <StatCard icon={Star}        label="Note moyenne"       value={`${Number(stats.note_moyenne || 0).toFixed(1)} ★`}      color="bg-amber-100 text-amber-600"  />
            </div>
          )}

          {/* Top produits */}
          {stats.top_produits?.length > 0 && (
            <div className="card p-6">
              <h2 className="font-serif font-bold text-lg text-gray-800 mb-4">Top produits</h2>
              <div className="space-y-3">
                {stats.top_produits.map((p, i) => (
                  <div key={p.id} className="flex items-center gap-4">
                    <span className="w-6 text-center font-bold text-gray-400 text-sm">#{i + 1}</span>
                    <div className="flex-1">
                      <div className="font-medium text-gray-800 text-sm">{p.nom}</div>
                      <div className="text-xs text-gray-500">{p.nb_vendus} vendu(s)</div>
                    </div>
                    <div className="font-bold text-primary">{Number(p.revenu).toFixed(0)} €</div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Commandes récentes */}
          <div className="card p-6">
            <h2 className="font-serif font-bold text-lg text-gray-800 mb-4">Commandes récentes</h2>
            {(stats.commandes_recentes?.length > 0) ? (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left border-b border-secondary-200">
                      <th className="pb-3 font-semibold text-gray-600">N°</th>
                      <th className="pb-3 font-semibold text-gray-600">Client</th>
                      <th className="pb-3 font-semibold text-gray-600">Total</th>
                      <th className="pb-3 font-semibold text-gray-600">Date</th>
                      <th className="pb-3 font-semibold text-gray-600">Statut</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-secondary-100">
                    {stats.commandes_recentes.slice(0, 5).map((order) => (
                      <tr key={order.id} className="hover:bg-secondary-50 transition-colors">
                        <td className="py-3 font-mono text-gray-600">#{order.id}</td>
                        <td className="py-3">{order.client_prenom} {order.client_nom}</td>
                        <td className="py-3 font-semibold">{Number(order.montant_total).toFixed(2)} €</td>
                        <td className="py-3 text-gray-500">{new Date(order.created_at).toLocaleDateString('fr-FR')}</td>
                        <td className="py-3"><StatusBadge statut={order.statut} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="text-gray-400 text-sm text-center py-6">Aucune commande pour l'instant.</p>
            )}
          </div>
        </div>
      )}

      {/* ── Products ─────────────────────────────────────────────────────── */}
      {activeTab === 'products' && (
        <div>
          <div className="flex justify-between items-center mb-5">
            <h2 className="font-serif font-bold text-xl text-gray-800">Mes produits</h2>
            <button onClick={() => { setEditingProduct(null); setShowProductForm(true); }}
              className="btn-primary flex items-center gap-2 text-sm">
              <Plus size={15} /> Ajouter un produit
            </button>
          </div>

          {productsLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="card p-4 animate-pulse flex gap-4">
                  <div className="w-14 h-14 bg-secondary-300 rounded-lg flex-shrink-0" />
                  <div className="flex-1 space-y-2">
                    <div className="h-4 bg-secondary-300 rounded w-1/3" />
                    <div className="h-3 bg-secondary-300 rounded w-1/4" />
                  </div>
                </div>
              ))}
            </div>
          ) : products.length === 0 ? (
            <div className="card p-12 text-center">
              <Package size={40} className="mx-auto text-gray-300 mb-3" />
              <p className="text-gray-500">Aucun produit. Commencez par en créer un !</p>
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
                  {products.map((product) => {
                    const imgs = typeof product.images === 'string' ? JSON.parse(product.images || '[]') : (product.images || []);
                    return (
                      <tr key={product.id} className="hover:bg-secondary-50 transition-colors">
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-3">
                            {imgs[0] ? (
                              <img src={imgs[0]} alt={product.nom}
                                className="w-10 h-10 object-cover rounded-lg border border-secondary-200 flex-shrink-0" />
                            ) : (
                              <div className="w-10 h-10 bg-secondary-200 rounded-lg flex items-center justify-center flex-shrink-0">
                                <ImageIcon size={16} className="text-gray-400" />
                              </div>
                            )}
                            <span className="font-medium text-gray-800">{product.nom}</span>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-gray-600 capitalize">{product.categorie_nom || product.categorie || '–'}</td>
                        <td className="px-4 py-3 font-semibold text-primary">{Number(product.prix).toFixed(2)} €</td>
                        <td className="px-4 py-3">
                          <span className={`font-medium ${product.stock > 0 ? 'text-green-600' : 'text-red-500'}`}>
                            {product.stock > 0 ? product.stock : 'Épuisé'}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex gap-2">
                            <button onClick={() => { setEditingProduct(product); setShowProductForm(true); }}
                              className="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded transition-colors" title="Modifier">
                              <Edit2 size={15} />
                            </button>
                            <button onClick={() => { if (window.confirm('Supprimer ce produit ?')) deleteMutation.mutate(product.id); }}
                              className="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                              <Trash2 size={15} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {showProductForm && (
            <ProductForm
              product={editingProduct}
              boutiqueId={boutique?.id}
              onClose={() => setShowProductForm(false)}
              onSaved={() => setShowProductForm(false)}
            />
          )}
        </div>
      )}

      {/* ── Orders ───────────────────────────────────────────────────────── */}
      {activeTab === 'orders' && (
        <div>
          <h2 className="font-serif font-bold text-xl text-gray-800 mb-5">Mes commandes</h2>
          {ordersLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => <div key={i} className="card p-4 animate-pulse h-16" />)}
            </div>
          ) : orders.length === 0 ? (
            <div className="card p-12 text-center">
              <ShoppingBag size={40} className="mx-auto text-gray-300 mb-3" />
              <p className="text-gray-500">Aucune commande reçue pour l'instant.</p>
            </div>
          ) : (
            <div className="card overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-secondary-100">
                  <tr className="text-left">
                    <th className="px-4 py-3 font-semibold text-gray-600">N°</th>
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
                      <td className="px-4 py-3">{order.client_prenom || order.client?.prenom || ''} {order.client_nom || order.client?.nom || '–'}</td>
                      <td className="px-4 py-3 text-gray-500">{new Date(order.created_at).toLocaleDateString('fr-FR')}</td>
                      <td className="px-4 py-3 text-gray-600">{order.nb_articles || '–'}</td>
                      <td className="px-4 py-3 font-semibold">{Number(order.montant_total ?? order.total ?? 0).toFixed(2)} €</td>
                      <td className="px-4 py-3"><StatusBadge statut={order.statut} /></td>
                      <td className="px-4 py-3">
                        <select value={order.statut}
                          onChange={(e) => updateOrderStatus.mutate({ id: order.id, status: e.target.value })}
                          className="text-xs border border-secondary-300 rounded-lg px-2 py-1 bg-white cursor-pointer focus:outline-none">
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
          )}
        </div>
      )}

      {/* ── Boutique ─────────────────────────────────────────────────────── */}
      {activeTab === 'boutique' && (
        <div className="max-w-xl">
          <h2 className="font-serif font-bold text-xl text-gray-800 mb-5">Paramètres de ma boutique</h2>
          <div className="card p-6 space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Nom de la boutique *</label>
              <input value={boutiqueForm.nom} onChange={(e) => bUp('nom')(e.target.value)}
                className="input-field" placeholder="Ma superbe boutique" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
              <textarea value={boutiqueForm.description} onChange={(e) => bUp('description')(e.target.value)}
                rows={4} className="input-field resize-none"
                placeholder="Décrivez votre boutique, votre savoir-faire…" />
            </div>

            <ImageUploader value={boutiqueForm.image} onChange={bUp('image')} label="Logo / image de la boutique" />

            <button onClick={() => saveBoutiqueMutation.mutate()}
              disabled={saveBoutiqueMutation.isPending || !boutiqueForm.nom.trim()}
              className="btn-primary w-full">
              {saveBoutiqueMutation.isPending ? 'Sauvegarde…' : 'Enregistrer les modifications'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
