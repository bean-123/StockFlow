import './App.css';
import { api } from './services/api';

function App() {

  const data = api.getProducts() || [];
  console.log(data.data);

  return (
    <>
     {data.data.map((product) => (
      <div key={product.id}>{product.name}</div>
     ))}
    </>
  )
}

export default App
