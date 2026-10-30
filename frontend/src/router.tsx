import { createBrowserRouter, Navigate } from "react-router-dom"
import HomePage from "./pages/public/HomePage"
import LoginPage from "./pages/auth/LoginPage"
import DashboardPage from "./pages/app/DashboardPage"
import ProductsPage from "./pages/app/ProductsPage"
import UsersPage from "./pages/app/UsersPage"
import PlatformLandingPage from "./pages/platform/LandingPage"
import { ProtectedRoute } from "./components/layout/ProtectedRoute"

export const router = createBrowserRouter([
  {
    path: "/",
    element: <HomePage />,
  },
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    path: "/app",
    element: <ProtectedRoute><DashboardPage /></ProtectedRoute>,
  },
  {
    path: "/app/products",
    element: <ProtectedRoute><ProductsPage /></ProtectedRoute>,
  },
  {
    path: "/app/users",
    element: <ProtectedRoute><UsersPage /></ProtectedRoute>,
  },
  {
    path: "/app/*",
    element: <Navigate to="/app" replace />,
  },
  {
    path: "/platform",
    element: <PlatformLandingPage />,
  },
  {
    path: "*",
    element: <Navigate to="/" replace />,
  },
])
