Rails.application.routes.draw do
  get "up" => "rails/health#show", as: :rails_health_check

  namespace :api do
    namespace :v1 do
      get "health", to: "health#show"
      get "fiis", to: "fiis#index"
      get "fiis/:ticker", to: "fiis#show"

      get "acoes", to: "acoes#index"
      get "acoes/:ticker", to: "acoes#show"

      get "ets", to: "ets#index"
      get "ets/:ticker", to: "ets#show"

      get "alertas", to: "alertas#index"
    end
  end
end
