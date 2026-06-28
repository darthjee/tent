import { HashRouter, Routes, Route } from 'react-router-dom';
import PersonList from './PersonList';
import PersonForm from './PersonForm';
import PersonPage from './PersonPage';
import EditPersonPage from './EditPersonPage';
import UploadPersonPhotoPage from './UploadPersonPhotoPage';

export default function App() {
  return (
    <HashRouter>
      <Routes>
        <Route path="/persons" element={<><PersonForm /><PersonList /></>} />
        <Route path="/persons/:id/edit" element={<EditPersonPage />} />
        <Route path="/persons/:id/upload_photo" element={<UploadPersonPhotoPage />} />
        <Route path="/persons/:id" element={<PersonPage />} />
      </Routes>
    </HashRouter>
  );
}
