  function getLocation()
       {
      if (navigator.geolocation)
      {
          navigator.geolocation.getCurrentPosition(showPosition);
     }
      else{alert("Geolocation is not supported by this browser.");}
  }
function showPosition(position)
  {
  document.getElementById("geo_latitude").value = position.coords.latitude;
      document.getElementById("geo_longitude").value = position.coords.longitude;
  document.getElementById("geo_address").value = json.address.road + ',' + json.address.city;

  }

