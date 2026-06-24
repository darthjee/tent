import { useState } from 'react';
import { PersonClient } from '../clients/PersonClient';

export default function PhotoUploadForm({ personId, onSuccess, onCancel }) {
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setUploading(true);
    setError(null);
    try {
      await (new PersonClient()).uploadPhoto(personId, file);
      onSuccess();
    } catch (err) {
      setError(err.message);
      setUploading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="d-flex align-items-center gap-2 mt-1">
      <input
        type="file"
        accept="image/*"
        className="form-control form-control-sm"
        onChange={(e) => setFile(e.target.files[0])}
        required
      />
      <button
        type="submit"
        className="btn btn-sm btn-primary"
        disabled={!file || uploading}
      >
        {uploading ? 'Uploading...' : 'Upload'}
      </button>
      <button
        type="button"
        className="btn btn-sm btn-secondary"
        onClick={onCancel}
      >
        Cancel
      </button>
      {error && <span className="text-danger small">{error}</span>}
    </form>
  );
}
