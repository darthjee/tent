import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { PersonClient } from '../clients/PersonClient';
import PhotoUploadForm from './PhotoUploadForm';

const UploadPersonPhotoPage = () => {
  const { id } = useParams();
  const navigate = useNavigate();

  const [person, setPerson] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    (new PersonClient()).get(id)
      .then((data) => {
        setPerson(data);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }, [id]);

  const handleSuccess = () => navigate(`/persons/${id}`);
  const handleCancel = () => navigate(`/persons/${id}`);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div className="container mt-4">
      <div className="row justify-content-center">
        <div className="col-md-6">
          <div className="card">
            <div className="card-header">
              <h4 className="mb-0">
                Upload Photo for {person.first_name} {person.last_name}
              </h4>
            </div>
            <div className="card-body">
              <PhotoUploadForm
                personId={id}
                onSuccess={handleSuccess}
                onCancel={handleCancel}
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default UploadPersonPhotoPage;
