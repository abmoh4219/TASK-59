import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getResources, getMyBookings, createBooking, cancelBooking } from '../../api/bookings';
import type { Resource, Booking } from '../../types';
import { CalendarRange, Users, Plus, Trash2, MapPin, Loader2, Package } from 'lucide-react';

export default function BookingPage() {
  const queryClient = useQueryClient();
  const [selectedResource, setSelectedResource] = useState<Resource | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({
    startDatetime: '',
    endDatetime: '',
    purpose: '',
    travelers: '',
  });
  const [error, setError] = useState<string | null>(null);

  const { data: resources, isLoading: resourcesLoading } = useQuery({
    queryKey: ['resources'],
    queryFn: getResources,
  });

  const { data: bookings, isLoading: bookingsLoading } = useQuery({
    queryKey: ['bookings'],
    queryFn: getMyBookings,
  });

  const createMutation = useMutation({
    mutationFn: createBooking,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] });
      setShowForm(false);
      setSelectedResource(null);
      setFormData({ startDatetime: '', endDatetime: '', purpose: '', travelers: '' });
      setError(null);
    },
    onError: (err: unknown) => {
      const axErr = err as { response?: { data?: { error?: string } } };
      setError(axErr.response?.data?.error || 'Failed to create booking');
    },
  });

  const cancelMutation = useMutation({
    mutationFn: cancelBooking,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['bookings'] }),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    if (!selectedResource) return;

    const travelerIds = formData.travelers
      .split(',')
      .map((t) => parseInt(t.trim(), 10))
      .filter((n) => !isNaN(n));

    createMutation.mutate({
      resourceId: selectedResource.id,
      startDatetime: new Date(formData.startDatetime).toISOString(),
      endDatetime: new Date(formData.endDatetime).toISOString(),
      purpose: formData.purpose,
      travelers: travelerIds,
      clientKey: crypto.randomUUID(),
    });
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-white">Bookings</h1>
        <p className="text-sm text-gray-400 mt-1">Reserve resources for trips and outings</p>
      </div>

      {/* Resource Catalog */}
      <div>
        <h2 className="text-lg font-semibold text-white mb-4">Available Resources</h2>
        {resourcesLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="bg-surface-card border border-surface-border rounded-xl p-6 h-40 animate-pulse" />
            ))}
          </div>
        ) : resources && resources.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {resources.map((resource) => (
              <div
                key={resource.id}
                className="bg-surface-card border border-surface-border rounded-xl p-5 hover:border-accent/40 hover:shadow-glow transition-all"
              >
                <div className="flex items-start justify-between mb-3">
                  <div className="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
                    <Package size={18} className="text-accent-light" />
                  </div>
                  <span className="text-xs px-2 py-1 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">
                    {resource.type}
                  </span>
                </div>
                <h3 className="text-base font-semibold text-white mb-1">{resource.name}</h3>
                <p className="text-xs text-gray-400 mb-3 line-clamp-2">{resource.description}</p>

                <div className="flex items-center gap-4 text-xs text-gray-500 mb-4">
                  <div className="flex items-center gap-1">
                    <Users size={12} />
                    Capacity {resource.capacity}
                  </div>
                  <div className="flex items-center gap-1">
                    <MapPin size={12} />
                    {resource.costCenter}
                  </div>
                </div>

                <button
                  onClick={() => {
                    setSelectedResource(resource);
                    setShowForm(true);
                  }}
                  disabled={!resource.isAvailable}
                  className="w-full flex items-center justify-center gap-2 py-2 px-3 bg-accent/10 hover:bg-accent/20 border border-accent/20 rounded-lg text-accent-light text-sm font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <Plus size={14} />
                  {resource.isAvailable ? 'Book Resource' : 'Unavailable'}
                </button>
              </div>
            ))}
          </div>
        ) : (
          <div className="bg-surface-card border border-surface-border rounded-xl p-8 text-center">
            <Package size={40} className="mx-auto text-gray-600 mb-3" />
            <p className="text-gray-400">No bookable resources available</p>
          </div>
        )}
      </div>

      {/* Booking Form Modal */}
      {showForm && selectedResource && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
          <div className="bg-surface-card border border-surface-border rounded-2xl p-6 w-full max-w-md">
            <h3 className="text-lg font-semibold text-white mb-1">Book {selectedResource.name}</h3>
            <p className="text-sm text-gray-400 mb-5">Cost center: {selectedResource.costCenter}</p>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-1.5">Start Date & Time</label>
                <input
                  type="datetime-local"
                  value={formData.startDatetime}
                  onChange={(e) => setFormData({ ...formData, startDatetime: e.target.value })}
                  className="w-full px-3 py-2 bg-surface border border-surface-border rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-accent"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-1.5">End Date & Time</label>
                <input
                  type="datetime-local"
                  value={formData.endDatetime}
                  onChange={(e) => setFormData({ ...formData, endDatetime: e.target.value })}
                  className="w-full px-3 py-2 bg-surface border border-surface-border rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-accent"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-1.5">Purpose</label>
                <textarea
                  value={formData.purpose}
                  onChange={(e) => setFormData({ ...formData, purpose: e.target.value })}
                  rows={3}
                  className="w-full px-3 py-2 bg-surface border border-surface-border rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-accent"
                  placeholder="Purpose of booking..."
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-1.5">
                  Traveler IDs (comma-separated, optional)
                </label>
                <input
                  type="text"
                  value={formData.travelers}
                  onChange={(e) => setFormData({ ...formData, travelers: e.target.value })}
                  className="w-full px-3 py-2 bg-surface border border-surface-border rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-accent"
                  placeholder="e.g., 4, 5, 6"
                />
                <p className="text-xs text-gray-500 mt-1">Leave empty to allocate to yourself only</p>
              </div>

              {error && (
                <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-sm text-red-300">
                  {error}
                </div>
              )}

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    setShowForm(false);
                    setError(null);
                  }}
                  className="flex-1 py-2 px-4 bg-surface border border-surface-border rounded-lg text-gray-300 text-sm font-medium hover:bg-surface-hover transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending}
                  className="flex-1 py-2 px-4 bg-gradient-to-r from-accent to-accent-dark rounded-lg text-white text-sm font-semibold disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {createMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : null}
                  Book Now
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* My Bookings */}
      <div>
        <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
          <CalendarRange size={18} />
          My Bookings
        </h2>
        {bookingsLoading ? (
          <div className="space-y-2">
            {[1, 2].map((i) => (
              <div key={i} className="h-20 bg-surface-card border border-surface-border rounded-xl animate-pulse" />
            ))}
          </div>
        ) : bookings && bookings.length > 0 ? (
          <div className="space-y-3">
            {bookings.map((booking: Booking) => (
              <div
                key={booking.id}
                className="bg-surface-card border border-surface-border rounded-xl p-4 flex items-start justify-between gap-4"
              >
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <h3 className="text-sm font-semibold text-white">{booking.resourceName}</h3>
                    <span
                      className={`text-xs px-2 py-0.5 rounded-full border ${
                        booking.status === 'active'
                          ? 'bg-green-500/10 text-green-400 border-green-500/20'
                          : 'bg-gray-500/10 text-gray-400 border-gray-500/20'
                      }`}
                    >
                      {booking.status}
                    </span>
                  </div>
                  <p className="text-xs text-gray-400 mb-1">
                    {new Date(booking.startDatetime).toLocaleString()} &rarr;{' '}
                    {new Date(booking.endDatetime).toLocaleString()}
                  </p>
                  <p className="text-xs text-gray-500 line-clamp-1">{booking.purpose}</p>
                  {booking.allocations && booking.allocations.length > 0 && (
                    <p className="text-xs text-gray-500 mt-1">
                      {booking.allocations.length} traveler(s)
                    </p>
                  )}
                </div>
                {booking.status === 'active' && (
                  <button
                    onClick={() => cancelMutation.mutate(booking.id)}
                    disabled={cancelMutation.isPending}
                    className="p-2 text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition"
                    title="Cancel booking"
                  >
                    <Trash2 size={16} />
                  </button>
                )}
              </div>
            ))}
          </div>
        ) : (
          <div className="bg-surface-card border border-surface-border rounded-xl p-8 text-center">
            <CalendarRange size={40} className="mx-auto text-gray-600 mb-3" />
            <p className="text-gray-400">No bookings yet</p>
          </div>
        )}
      </div>
    </div>
  );
}
