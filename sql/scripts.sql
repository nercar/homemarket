USE BDES_POS
GO
-- Agrega el campo vendedor a las facturas de pickup
ALTER TABLE [dbo].[DBVENTAS_TMP] ADD [VENDEDOR] numeric(18) DEFAULT 2 NOT NULL


CREATE TABLE [dbo].[HMDespachoCab] (
  [id] int  IDENTITY(1,1) NOT NULL,
  [fecha] datetime DEFAULT getdate() NOT NULL,
  [usuario] varchar(255) COLLATE Modern_Spanish_CI_AS  NOT NULL,
  [status] int DEFAULT 1 NOT NULL,
  [documento] numeric(18)  NOT NULL,
  [caja] int  NOT NULL,
  [localidad] int  NOT NULL,
  [fecha_doc] datetime  NOT NULL,
  CONSTRAINT [PK__HMDespac__3213E83F8EE38A9D] PRIMARY KEY CLUSTERED ([id])
WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON)  
ON [PRIMARY]
)  
ON [PRIMARY]
GO

ALTER TABLE [dbo].[HMDespachoCab] SET (LOCK_ESCALATION = TABLE)

CREATE TABLE [dbo].[HMDespachoDet] (
  [id] int  IDENTITY(1,1) NOT NULL,
  [id_cab] int NOT NULL,
  [status] int DEFAULT 1 NOT NULL,
  [codigo] numeric(18)  NOT NULL,
  [vendido] decimal(18,3)  NOT NULL,
  [cantidad] decimal(18,3)  NOT NULL,
  [documento] numeric(18)  NOT NULL,
  [localidad] int  NOT NULL,
  [caja] int  NOT NULL,
  CONSTRAINT [PK__HMDespac__3213E83FC39FFF19] PRIMARY KEY CLUSTERED ([id])
WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON)  
ON [PRIMARY]
)  
ON [PRIMARY]
GO

ALTER TABLE [dbo].[HMDespachoDet] SET (LOCK_ESCALATION = TABLE)

