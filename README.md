WooTan
======

Wootan provides a number of shipping options to the customer through the Woocommerce Shipping API based on a series of very specific criteria. The shipping options that are shown to the customer are based on criteria that include: 
The role of the user
Whether the Item contains items labelled as “dangerous” (meta field wootan_danger == “Y”)
  The weights and volumes of individual items
  The combined weight and volume of an order
  The total cost of the order
  The Destination country
The plugin also allows for custom functions to calculate the cost of a package based on these criteria
