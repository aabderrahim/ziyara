
import './App.css';
import { useEffect, useState } from 'react';
import api from './api';
import AppRoutes from './routes/AppRoutes';



function App() {
  const [tours, setTours] = useState([]);

   useEffect(() => {
    api.get('/tours')
      .then(response => setTours(response.data))
      .catch(error => console.error(error));
  }, []);


  return (
    
    <div className="App">
    <AppRoutes />
      <h1>Ziyara Tours</h1>
      <ul>
        {tours.map(tour => (
          <li key={tour.id}>{tour.name}</li>
        ))}
      </ul>;
    </div>
  );
}

export default App;

